<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimBatch;
use App\Models\InsuranceClaimPayment;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\PatientCoverage;
use App\Models\PayerPlanTariff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InsuranceClaimWorkflowService
{
    public function createFromInvoice(Invoice $invoice, PatientCoverage $coverage, User $actor): InsuranceClaim
    {
        abort_unless($invoice->hospital_id === $coverage->hospital_id, 422, 'Invoice and coverage belong to different hospitals.');
        abort_unless($invoice->patient_id === $coverage->patient_id, 422, 'Coverage does not belong to the invoice patient.');
        abort_unless($coverage->isCurrentlyValid(), 422, 'Coverage is not currently active and valid.');
        abort_unless(in_array($invoice->status, ['issued'], true), 422, 'Only an issued invoice can be prepared as a payer claim.');

        $invoice->loadMissing('lines');
        abort_if($invoice->lines->isEmpty(), 422, 'The invoice has no lines to claim.');

        return DB::transaction(function () use ($invoice, $coverage, $actor): InsuranceClaim {
            $number = $this->allocateNumber($invoice->hospital_id, 'insurance_claim_number', 'Insurance claim', 'CLM');
            $claim = InsuranceClaim::create([
                'hospital_id' => $invoice->hospital_id,
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'patient_coverage_id' => $coverage->id,
                'payer_organization_id' => $coverage->payer_organization_id,
                'payer_plan_id' => $coverage->payer_plan_id,
                'claim_number' => $number,
                'status' => 'draft',
                'currency' => $invoice->currency,
                'created_by' => $actor->id,
            ]);

            $claimed = 0;
            foreach ($invoice->lines as $line) {
                $lineAmount = $line->total_minor;
                if ($line->billable_service_id) {
                    $tariff = $this->effectiveTariff(
                        $coverage->payer_plan_id,
                        $line->billable_service_id,
                        $invoice->facility_id,
                        $invoice->issued_at?->toDateString() ?? today()->toDateString()
                    );
                    abort_unless($tariff, 422, "No effective payer tariff is configured for {$line->service_name}.");
                    abort_unless($tariff->currency === $invoice->currency, 422, "Payer tariff currency does not match invoice currency for {$line->service_name}.");
                    $lineAmount = $tariff->amount_minor * $line->quantity;
                }

                $claim->lines()->create([
                    'invoice_line_id' => $line->id,
                    'billable_service_id' => $line->billable_service_id,
                    'service_code' => $line->service_code,
                    'service_name' => $line->service_name,
                    'quantity' => $line->quantity,
                    'claimed_minor' => $lineAmount,
                ]);
                $claimed += $lineAmount;
            }

            $claim->update(['claimed_minor' => $claimed, 'outstanding_minor' => $claimed]);
            $this->event($claim, 'created', null, 'draft', $actor, ['claimed_minor' => $claimed]);
            app(AuditService::class)->record('insurance.claim_created', $claim, null, $claim->fresh()->toArray(), actor: $actor);

            return $claim->fresh(['lines']);
        });
    }

    public function submit(InsuranceClaim $claim, array $data, User $actor): InsuranceClaim
    {
        $this->assertAccess($claim, $actor);
        abort_unless(in_array($claim->status, ['draft', 'rejected'], true), 422, 'Only draft or rejected claims can be submitted/resubmitted.');
        $before = $claim->toArray();
        $from = $claim->status;
        $round = $from === 'rejected' ? $claim->submission_round + 1 : $claim->submission_round;
        $claim->update([
            'submission_round' => $round,
            'status' => 'submitted',
            'external_reference' => $data['external_reference'] ?? $claim->external_reference,
            'submission_notes' => $data['submission_notes'] ?? null,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'last_resubmitted_at' => $from === 'rejected' ? now() : $claim->last_resubmitted_at,
        ]);
        $this->event($claim, $from === 'rejected' ? 'resubmitted' : 'submitted', $from, 'submitted', $actor, ['submission_round' => $round]);
        app(AuditService::class)->record('insurance.claim_'.($from === 'rejected' ? 'resubmitted' : 'submitted'), $claim, $before, $claim->fresh()->toArray(), actor: $actor);
        return $claim->fresh();
    }

    public function decide(InsuranceClaim $claim, array $data, User $actor): InsuranceClaim
    {
        $this->assertAccess($claim, $actor);
        abort_unless($claim->status === 'submitted', 422, 'Only submitted claims can receive a decision.');
        $claim->loadMissing('lines');

        $status = $data['status'];
        if ($status === 'rejected') {
            foreach ($claim->lines as $line) $line->update(['approved_minor' => 0, 'decision_reason' => $data['decision_notes']]);
        } elseif ($status === 'approved') {
            foreach ($claim->lines as $line) $line->update(['approved_minor' => $line->claimed_minor, 'decision_reason' => null]);
        } else {
            $decisions = collect($data['line_decisions'] ?? [])->keyBy(fn ($entry) => (int)($entry['line_id'] ?? 0));
            abort_if($decisions->isEmpty(), 422, 'Line decisions are required for a partial approval.');
            foreach ($claim->lines as $line) {
                $decision = $decisions->get($line->id);
                $approved = (int)($decision['approved_minor'] ?? 0);
                abort_if($approved < 0 || $approved > $line->claimed_minor, 422, 'Approved line amount is outside the claimed amount.');
                $line->update(['approved_minor' => $approved, 'decision_reason' => $decision['reason'] ?? null]);
            }
        }

        $approved = (int)$claim->lines()->sum('approved_minor');
        abort_if($status === 'partially_approved' && ($approved <= 0 || $approved >= $claim->claimed_minor), 422, 'Partial approval total must be greater than zero and less than the claimed amount.');

        $before = $claim->toArray();
        $claim->update([
            'status' => $status,
            'approved_minor' => $approved,
            'outstanding_minor' => max(0, $approved - $claim->paid_minor),
            'decision_notes' => $data['decision_notes'],
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);
        $this->event($claim, 'decision_recorded', 'submitted', $status, $actor, ['approved_minor' => $approved], $data['decision_notes']);
        app(AuditService::class)->record('insurance.claim_'.$status, $claim, $before, $claim->fresh()->toArray(), actor: $actor);
        return $claim->fresh(['lines']);
    }

    public function recordPayment(InsuranceClaim $claim, array $data, User $actor): InsuranceClaimPayment
    {
        $this->assertAccess($claim, $actor);
        abort_unless(in_array($claim->status, ['approved', 'partially_approved', 'partially_paid'], true), 422, 'Payment can only be recorded against an approved claim.');
        $amount = (int)$data['amount_minor'];
        abort_if($amount <= 0 || $amount > $claim->outstanding_minor, 422, 'Payment amount exceeds the claim outstanding balance.');

        return DB::transaction(function () use ($claim, $data, $actor, $amount): InsuranceClaimPayment {
            $payment = $claim->payments()->create([
                'hospital_id' => $claim->hospital_id,
                'amount_minor' => $amount,
                'reference' => $data['reference'] ?? null,
                'received_on' => $data['received_on'],
                'recorded_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
            $paid = $claim->paid_minor + $amount;
            $outstanding = max(0, $claim->approved_minor - $paid);
            $from = $claim->status;
            $to = $outstanding === 0 ? 'paid' : 'partially_paid';
            $claim->update(['paid_minor' => $paid, 'outstanding_minor' => $outstanding, 'status' => $to]);
            $this->event($claim, 'payment_recorded', $from, $to, $actor, ['amount_minor' => $amount, 'reference' => $data['reference'] ?? null]);
            app(AuditService::class)->record('insurance.claim_payment_recorded', $payment, null, $payment->toArray(), actor: $actor);
            return $payment;
        });
    }

    public function createBatch(int $hospitalId, int $payerId, string $currency, array $claimIds, array $data, User $actor): InsuranceClaimBatch
    {
        $claims = InsuranceClaim::where('hospital_id', $hospitalId)
            ->where('payer_organization_id', $payerId)
            ->where('currency', $currency)
            ->whereIn('id', $claimIds)
            ->where('status', 'submitted')
            ->whereDoesntHave('events', fn ($q) => $q->where('action', 'batched'))
            ->get();

        abort_if($claims->count() !== count(array_unique(array_map('intval',$claimIds))), 422, 'Every selected claim must be submitted, belong to the payer/currency, and not already be batched.');

        return DB::transaction(function () use ($hospitalId,$payerId,$currency,$claims,$data,$actor): InsuranceClaimBatch {
            $batch=InsuranceClaimBatch::create([
                'hospital_id'=>$hospitalId,'payer_organization_id'=>$payerId,
                'batch_number'=>$this->allocateNumber($hospitalId,'insurance_claim_batch_number','Insurance claim batch','CLB'),
                'status'=>'draft','currency'=>$currency,'claim_count'=>$claims->count(),
                'total_claimed_minor'=>$claims->sum('claimed_minor'),'created_by'=>$actor->id,'notes'=>$data['notes']??null,
            ]);
            $batch->claims()->sync($claims->pluck('id')->all());
            foreach($claims as $claim) $this->event($claim,'batched',$claim->status,$claim->status,$actor,['batch_number'=>$batch->batch_number]);
            app(AuditService::class)->record('insurance.claim_batch_created',$batch,null,$batch->fresh()->toArray(),actor:$actor);
            return $batch->fresh('claims');
        });
    }

    public function submitBatch(InsuranceClaimBatch $batch, array $data, User $actor): InsuranceClaimBatch
    {
        abort_unless($batch->hospital_id === $actor->hospitalId() || $actor->hasRole('superadmin'),403);
        abort_unless($batch->status==='draft',422,'Only a draft batch can be submitted.');
        $before=$batch->toArray();
        $batch->update(['status'=>'submitted','external_reference'=>$data['external_reference']??null,'submitted_at'=>now(),'submitted_by'=>$actor->id]);
        app(AuditService::class)->record('insurance.claim_batch_submitted',$batch,$before,$batch->fresh()->toArray(),actor:$actor);
        return $batch->fresh();
    }

    private function effectiveTariff(int $planId, int $serviceId, ?int $facilityId, string $date): ?PayerPlanTariff
    {
        $base=PayerPlanTariff::where('payer_plan_id',$planId)->where('billable_service_id',$serviceId)
            ->where('is_active',true)->whereDate('effective_from','<=',$date)
            ->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$date));
        if($facilityId){
            $specific=(clone $base)->where('facility_id',$facilityId)->latest('effective_from')->first();
            if($specific) return $specific;
        }
        return (clone $base)->whereNull('facility_id')->latest('effective_from')->first();
    }

    private function allocateNumber(int $hospitalId, string $key, string $label, string $prefix): string
    {
        $sequence=NumberSequence::firstOrCreate(
            ['hospital_id'=>$hospitalId,'facility_id'=>null,'key'=>$key],
            ['label'=>$label,'prefix'=>$prefix,'date_format'=>'Y','padding_length'=>6,'next_value'=>1,'issued_count'=>0,'status'=>'active']
        );
        return app(NumberSequenceService::class)->allocate($sequence);
    }

    private function event(InsuranceClaim $claim, string $action, ?string $from, ?string $to, User $actor, array $payload=[], ?string $reason=null): void
    {
        $claim->events()->create(['hospital_id'=>$claim->hospital_id,'actor_id'=>$actor->id,'action'=>$action,'from_status'=>$from,'to_status'=>$to,'payload'=>$payload ?: null,'reason'=>$reason,'occurred_at'=>now()]);
    }

    private function assertAccess(InsuranceClaim $claim, User $actor): void
    {
        abort_unless($claim->hospital_id === $actor->hospitalId() || $actor->hasRole('superadmin'),403);
    }
}
