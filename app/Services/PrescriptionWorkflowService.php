<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionAmendment;
use App\Models\PrescriptionDispense;
use App\Models\PrescriptionEvent;
use App\Models\PrescriptionItem;
use App\Models\PrescriptionReview;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrescriptionWorkflowService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly InventoryLedgerService $ledger,
        private readonly InvoiceWorkflowService $invoices,
        private readonly AuditService $audit,
    ) {}

    public function createDraft(array $data, User $actor): Prescription
    {
        return DB::transaction(function () use ($data, $actor): Prescription {
            $patient = Patient::where('hospital_id', $data['hospital_id'])->findOrFail($data['patient_id']);
            $encounter = isset($data['clinical_encounter_id']) ? ClinicalEncounter::where('hospital_id', $data['hospital_id'])->findOrFail($data['clinical_encounter_id']) : null;
            $prescription = Prescription::create([
                'hospital_id' => $data['hospital_id'],
                'facility_id' => $data['facility_id'],
                'patient_id' => $patient->id,
                'clinical_encounter_id' => $encounter?->id,
                'prescribing_clinician_id' => $data['prescribing_clinician_id'] ?? $actor->staffProfile?->id,
                'prescription_number' => $this->numbers->allocate(NumberSequence::where('hospital_id', $data['hospital_id'])->whereNull('facility_id')->where('key', 'prescription_number')->where('status', 'active')->firstOrFail()),
                'status' => 'draft',
                'clinical_note' => $data['clinical_note'] ?? null,
                'created_by' => $actor->id,
            ]);
            foreach ($data['items'] as $row) {
                $medicine = InventoryItem::where('hospital_id', $data['hospital_id'])->findOrFail($row['inventory_item_id']);
                PrescriptionItem::create([
                    'hospital_id' => $data['hospital_id'],
                    'prescription_id' => $prescription->id,
                    'inventory_item_id' => $medicine->id,
                    'inventory_unit_id' => $row['inventory_unit_id'] ?? $medicine->base_unit_id,
                    'medicine_name' => $medicine->name,
                    'dose' => $row['dose'],
                    'route' => $row['route'] ?? $medicine->route,
                    'frequency' => $row['frequency'] ?? null,
                    'duration' => $row['duration'] ?? null,
                    'quantity' => $row['quantity'],
                    'instructions' => $row['instructions'] ?? null,
                    'indication' => $row['indication'] ?? null,
                    'is_prn' => (bool) ($row['is_prn'] ?? false),
                    'prn_instructions' => $row['prn_instructions'] ?? null,
                ]);
            }
            $this->event($prescription, 'prescription.created', null, $prescription->fresh()->toArray(), $actor);

            return $prescription->refresh();
        });
    }

    public function sign(Prescription $prescription, User $actor): Prescription
    {
        abort_unless($prescription->status === 'draft', 422, 'Only draft prescriptions can be signed.');
        abort_if($prescription->items()->count() === 0, 422, 'Cannot sign an empty prescription.');
        $before = $prescription->toArray();
        $prescription->forceFill(['status' => 'signed', 'signed_by' => $actor->id, 'signed_at' => now()])->save();
        $this->event($prescription, 'prescription.signed', $before, $prescription->fresh()->toArray(), $actor);

        return $prescription->refresh();
    }

    public function transition(Prescription $prescription, string $status, User $actor, string $reason): Prescription
    {
        abort_unless(in_array($status, ['discontinued', 'cancelled'], true), 422, 'Invalid prescription transition.');
        abort_unless(in_array($prescription->status, ['draft', 'signed'], true), 422, 'Prescription cannot be changed.');
        $before = $prescription->toArray();
        $prescription->forceFill(['status' => $status, 'status_reason' => $reason])->save();
        $this->event($prescription, "prescription.{$status}", $before, $prescription->fresh()->toArray(), $actor, $reason);

        return $prescription->refresh();
    }

    public function amend(Prescription $prescription, array $data, User $actor): PrescriptionAmendment
    {
        abort_unless($prescription->status === 'signed', 422, 'Only signed prescriptions require append-only amendments.');
        $amendment = PrescriptionAmendment::create($data + ['hospital_id' => $prescription->hospital_id, 'prescription_id' => $prescription->id, 'authored_by' => $actor->id, 'authored_at' => now()]);
        $this->event($prescription, 'prescription.amended', null, $amendment->toArray(), $actor, $data['reason']);

        return $amendment;
    }

    public function review(Prescription $prescription, array $data, User $actor): PrescriptionReview
    {
        abort_unless($prescription->status === 'signed', 422, 'Only signed prescriptions can be reviewed.');
        abort_unless(in_array($data['action'], ['approved', 'clarification_requested', 'rejected', 'substitution_authorized'], true), 422, 'Invalid review action.');
        abort_if(in_array($data['action'], ['clarification_requested', 'rejected', 'substitution_authorized'], true) && empty($data['reason']), 422, 'Reason is required.');
        $review = PrescriptionReview::create($data + ['hospital_id' => $prescription->hospital_id, 'prescription_id' => $prescription->id, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->event($review, 'prescription.reviewed', null, $review->toArray(), $actor, $data['reason'] ?? null);

        return $review;
    }

    public function bill(Prescription $prescription, User $actor, string $currency = 'NGN'): Prescription
    {
        abort_unless($prescription->status === 'signed', 422, 'Only signed prescriptions can be billed.');

        return DB::transaction(function () use ($prescription, $actor, $currency): Prescription {
            $prescription = Prescription::whereKey($prescription->id)->lockForUpdate()->firstOrFail();
            $invoice = $prescription->invoice ?: $this->invoices->createDraft(['facility_id' => $prescription->facility_id, 'clinical_encounter_id' => $prescription->clinical_encounter_id, 'currency' => $currency], $prescription->patient, $actor);
            foreach ($prescription->items()->with('item.billableService')->get() as $item) {
                if (! $item->invoice_line_id && $item->item->billableService) {
                    $line = $this->invoices->addServiceLine($invoice->fresh(), $item->item->billableService, ['quantity' => (int) ceil((float) $item->quantity)], $actor);
                    $item->forceFill(['invoice_line_id' => $line->id])->save();
                }
            }
            $prescription->forceFill(['invoice_id' => $invoice->id])->save();
            $this->event($prescription, 'prescription.billed', null, $prescription->fresh()->toArray(), $actor);

            return $prescription->refresh();
        });
    }

    public function dispense(PrescriptionItem $item, InventoryLocation $location, InventoryBatch $batch, float|string $quantity, User $actor, ?string $instructions = null): PrescriptionDispense
    {
        abort_unless($item->prescription->status === 'signed', 422, 'Only signed prescriptions can be dispensed.');
        abort_unless($item->prescription->reviews()->whereIn('action', ['approved', 'substitution_authorized'])->exists(), 422, 'Pharmacist approval is required before dispensing.');
        abort_if(bccomp((string) $quantity, $item->outstandingQuantity(), 4) > 0, 422, 'Cannot dispense more than outstanding quantity.');
        abort_unless($batch->inventory_item_id === $item->inventory_item_id && $batch->isDispensableCandidate(), 422, 'Batch is not available for dispensing.');
        $balance = StockBalance::where('inventory_location_id', $location->id)->where('inventory_batch_id', $batch->id)->lockForUpdate()->first();
        abort_unless($balance && bccomp((string) $balance->quantity, (string) $quantity, 4) >= 0, 422, 'Insufficient batch stock.');

        return DB::transaction(function () use ($item, $location, $batch, $quantity, $actor, $instructions): PrescriptionDispense {
            $item = PrescriptionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            abort_if(bccomp((string) $quantity, $item->outstandingQuantity(), 4) > 0, 422, 'Cannot dispense more than outstanding quantity.');
            $movement = $this->ledger->postMovement('dispense', $item->item, $batch, $location, null, $item->unit, $quantity, $actor, 'Prescription dispense', $batch->unit_cost_minor, $batch->currency, $item);
            $dispense = PrescriptionDispense::create(['hospital_id' => $item->hospital_id, 'prescription_id' => $item->prescription_id, 'prescription_item_id' => $item->id, 'inventory_location_id' => $location->id, 'inventory_batch_id' => $batch->id, 'stock_movement_id' => $movement->id, 'quantity' => $quantity, 'action' => 'dispense', 'instructions' => $instructions, 'performed_by' => $actor->id, 'performed_at' => now()]);
            $item->forceFill(['dispensed_quantity' => bcadd((string) $item->dispensed_quantity, (string) $quantity, 4)])->save();
            $this->completeIfFilled($item->prescription);
            $this->event($dispense, 'prescription.dispensed', null, $dispense->toArray(), $actor);

            return $dispense;
        });
    }

    public function returnDispense(PrescriptionDispense $dispense, User $actor, string $reason): PrescriptionDispense
    {
        abort_unless($dispense->action === 'dispense', 422, 'Only dispense records can be returned.');
        abort_if(PrescriptionDispense::where('source_dispense_id', $dispense->id)->whereIn('action', ['return', 'reversal'])->exists(), 422, 'Dispense has already been returned or reversed.');

        return DB::transaction(function () use ($dispense, $actor, $reason): PrescriptionDispense {
            $item = PrescriptionItem::whereKey($dispense->prescription_item_id)->lockForUpdate()->firstOrFail();
            $movement = $this->ledger->postMovement('return', $item->item, $dispense->batch, null, InventoryLocation::findOrFail($dispense->inventory_location_id), $item->unit, $dispense->quantity, $actor, $reason, $dispense->batch->unit_cost_minor, $dispense->batch->currency, $dispense);
            $return = PrescriptionDispense::create(['hospital_id' => $dispense->hospital_id, 'prescription_id' => $dispense->prescription_id, 'prescription_item_id' => $dispense->prescription_item_id, 'inventory_location_id' => $dispense->inventory_location_id, 'inventory_batch_id' => $dispense->inventory_batch_id, 'stock_movement_id' => $movement->id, 'source_dispense_id' => $dispense->id, 'quantity' => $dispense->quantity, 'action' => 'return', 'reason' => $reason, 'performed_by' => $actor->id, 'performed_at' => now()]);
            $item->forceFill(['dispensed_quantity' => bcsub((string) $item->dispensed_quantity, (string) $dispense->quantity, 4)])->save();
            $item->prescription->forceFill(['status' => 'signed', 'completed_at' => null])->save();
            $this->event($return, 'prescription.returned', null, $return->toArray(), $actor, $reason);

            return $return;
        });
    }

    public function reverseDispense(PrescriptionDispense $dispense, User $actor, string $reason): PrescriptionDispense
    {
        abort_unless($dispense->action === 'dispense', 422, 'Only dispense records can be reversed.');
        abort_unless($dispense->stock_movement_id, 422, 'Dispense has no movement.');
        abort_if(PrescriptionDispense::where('source_dispense_id', $dispense->id)->whereIn('action', ['return', 'reversal'])->exists(), 422, 'Dispense has already been returned or reversed.');

        return DB::transaction(function () use ($dispense, $actor, $reason): PrescriptionDispense {
            $item = PrescriptionItem::whereKey($dispense->prescription_item_id)->lockForUpdate()->firstOrFail();
            $movement = $this->ledger->reverseMovement(StockMovement::findOrFail($dispense->stock_movement_id), $actor, $reason);
            $reversal = PrescriptionDispense::create(['hospital_id' => $dispense->hospital_id, 'prescription_id' => $dispense->prescription_id, 'prescription_item_id' => $dispense->prescription_item_id, 'inventory_location_id' => $dispense->inventory_location_id, 'inventory_batch_id' => $dispense->inventory_batch_id, 'stock_movement_id' => $movement->id, 'source_dispense_id' => $dispense->id, 'quantity' => $dispense->quantity, 'action' => 'reversal', 'reason' => $reason, 'performed_by' => $actor->id, 'performed_at' => now()]);
            $item->forceFill(['dispensed_quantity' => bcsub((string) $item->dispensed_quantity, (string) $dispense->quantity, 4)])->save();
            $item->prescription->forceFill(['status' => 'signed', 'completed_at' => null])->save();
            $this->event($reversal, 'prescription.dispense_reversed', null, $reversal->toArray(), $actor, $reason);

            return $reversal;
        });
    }

    private function completeIfFilled(Prescription $prescription): void
    {
        $fresh = $prescription->fresh('items');
        if ($fresh->items->every(fn ($item) => bccomp((string) $item->dispensed_quantity, (string) $item->quantity, 4) >= 0)) {
            $fresh->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        PrescriptionEvent::create(['hospital_id' => $subject->hospital_id, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'actor_id' => $actor->id, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
