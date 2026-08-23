<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\LabEvent;
use App\Models\LabReferenceRange;
use App\Models\LabReportAmendment;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Models\LabSpecimenType;
use App\Models\LabTest;
use App\Models\LabTestComponent;
use App\Models\LabTestProfile;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LaboratoryWorkflowService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly InvoiceWorkflowService $invoices,
        private readonly AuditService $audit,
        private readonly PatientActivity $activity,
    ) {}

    public function order(array $data, User $actor): LabRequest
    {
        return DB::transaction(function () use ($data, $actor): LabRequest {
            $patient = Patient::where('hospital_id', $data['hospital_id'])->findOrFail($data['patient_id']);
            $tests = $this->testsFromPayload($data);
            abort_if($tests->isEmpty(), 422, 'At least one laboratory test is required.');

            $request = LabRequest::create([
                'hospital_id' => $data['hospital_id'],
                'facility_id' => $data['facility_id'],
                'department_id' => $data['department_id'] ?? null,
                'patient_id' => $patient->id,
                'visit_id' => $data['visit_id'] ?? null,
                'clinical_encounter_id' => $data['clinical_encounter_id'] ?? null,
                'ordering_clinician_id' => $data['ordering_clinician_id'] ?? $actor->staffProfile?->id,
                'ordered_by' => $actor->id,
                'request_number' => $this->allocate($data['hospital_id'], 'lab_request_number'),
                'accession_number' => $this->allocate($data['hospital_id'], 'lab_accession_number'),
                'status' => 'ordered',
                'priority' => $data['priority'] ?? 'routine',
                'clinical_notes' => $data['clinical_notes'] ?? null,
                'ordered_at' => now(),
            ]);

            $invoice = null;
            foreach ($tests as $test) {
                $lineId = null;
                if ($test->billable_service_id) {
                    $invoice ??= $this->invoiceFor($request, $patient, $data, $actor);
                    $line = $this->invoices->addServiceLine($invoice->fresh(), $test->billableService, ['quantity' => 1], $actor);
                    $lineId = $line->id;
                }

                LabRequestTest::create([
                    'hospital_id' => $request->hospital_id,
                    'lab_request_id' => $request->id,
                    'lab_test_id' => $test->id,
                    'invoice_line_id' => $lineId,
                    'test_code' => $test->code,
                    'test_name' => $test->name,
                    'status' => 'ordered',
                    'component_snapshot' => $test->components->map(fn (LabTestComponent $component): array => [
                        'id' => $component->id,
                        'code' => $component->code,
                        'name' => $component->name,
                        'result_type' => $component->result_type,
                        'unit' => $component->unit?->code,
                    ])->values()->all(),
                ]);
            }

            if ($invoice) {
                $request->forceFill(['invoice_id' => $invoice->id])->save();
            }

            $this->event($request, 'lab.requested', null, 'ordered', null, $request->fresh()->toArray(), $actor);
            $this->activity->record($patient, 'lab.requested', $actor, ['request_number' => $request->request_number]);

            return $request->refresh();
        });
    }

    public function collectSpecimen(LabRequest $request, LabSpecimenType $type, User $actor): LabSpecimen
    {
        abort_unless(in_array($request->status, ['ordered', 'collection_pending', 'specimen_rejected'], true), 422, 'Specimen cannot be collected for this request state.');

        return DB::transaction(function () use ($request, $type, $actor): LabSpecimen {
            abort_unless($request->hospital_id === $type->hospital_id, 403);
            $specimen = LabSpecimen::create([
                'hospital_id' => $request->hospital_id,
                'lab_request_id' => $request->id,
                'lab_specimen_type_id' => $type->id,
                'label_number' => $this->allocate($request->hospital_id, 'lab_specimen_number'),
                'status' => 'collected',
                'collected_by' => $actor->id,
                'collected_at' => now(),
            ]);
            $this->transitionRequest($request, 'specimen_collected', 'collection_pending', $actor);
            $this->event($specimen, 'lab.specimen_collected', null, 'collected', null, $specimen->toArray(), $actor);

            return $specimen;
        });
    }

    public function receiveSpecimen(LabSpecimen $specimen, User $actor): LabSpecimen
    {
        abort_unless($specimen->status === 'collected', 422, 'Only collected specimens can be received.');

        return DB::transaction(function () use ($specimen, $actor): LabSpecimen {
            $before = $specimen->toArray();
            $specimen->forceFill(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now()])->save();
            $this->transitionRequest($specimen->request, 'specimen_received', 'received', $actor);
            $this->event($specimen, 'lab.specimen_received', 'collected', 'received', $before, $specimen->fresh()->toArray(), $actor);

            return $specimen->refresh();
        });
    }

    public function rejectSpecimen(LabSpecimen $specimen, User $actor, string $reason): LabSpecimen
    {
        abort_unless(in_array($specimen->status, ['collected', 'received'], true), 422, 'Only collected or received specimens can be rejected.');

        return DB::transaction(function () use ($specimen, $actor, $reason): LabSpecimen {
            $before = $specimen->toArray();
            $from = $specimen->status;
            $specimen->forceFill(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => $reason])->save();
            $this->transitionRequest($specimen->request, 'specimen_rejected', 'specimen_rejected', $actor, $reason);
            $this->event($specimen, 'lab.specimen_rejected', $from, 'rejected', $before, $specimen->fresh()->toArray(), $actor, $reason);

            return $specimen->refresh();
        });
    }

    public function enterResult(LabRequestTest $requestTest, LabTestComponent $component, array $data, User $actor): LabResult
    {
        abort_unless($requestTest->status !== 'approved', 422, 'Approved results require an amendment.');
        abort_unless($requestTest->lab_test_id === $component->lab_test_id, 403);

        return DB::transaction(function () use ($requestTest, $component, $data, $actor): LabResult {
            $range = $component->referenceRanges()->where('is_active', true)->first();
            [$flag, $critical] = $this->flag($component->result_type, $data, $range);
            $result = LabResult::create([
                'hospital_id' => $requestTest->hospital_id,
                'lab_request_id' => $requestTest->lab_request_id,
                'lab_request_test_id' => $requestTest->id,
                'lab_test_component_id' => $component->id,
                'lab_unit_id' => $component->lab_unit_id,
                'component_code' => $component->code,
                'component_name' => $component->name,
                'result_type' => $component->result_type,
                'numeric_value' => $data['numeric_value'] ?? null,
                'text_value' => $data['text_value'] ?? null,
                'qualitative_value' => $data['qualitative_value'] ?? null,
                'comment' => $data['comment'] ?? null,
                'reference_range_snapshot' => $range?->toArray(),
                'flag' => $flag,
                'is_critical' => $critical,
                'status' => 'draft',
                'entered_by' => $actor->id,
                'entered_at' => now(),
            ]);
            $requestTest->forceFill(['status' => 'result_draft'])->save();
            $this->transitionRequest($requestTest->request, 'result_entered', 'result_draft', $actor);
            $this->event($result, 'lab.result_entered', null, 'draft', null, $result->toArray(), $actor);

            return $result;
        });
    }

    public function verifyResult(LabResult $result, User $actor): LabResult
    {
        abort_unless($result->status === 'draft', 422, 'Only draft results can be verified.');

        return $this->resultTransition($result, 'lab.result_verified', 'verified', $actor);
    }

    public function approveResult(LabResult $result, User $actor): LabResult
    {
        abort_unless($result->status === 'verified', 422, 'Only verified results can be approved.');
        abort_if($result->entered_by === $actor->id && config('hms.lab.separate_entry_approval', true), 403, 'Result approver must be separate from entry user.');

        return $this->resultTransition($result, 'lab.result_approved', 'approved', $actor);
    }

    public function releaseRequest(LabRequest $request, User $actor): LabRequest
    {
        abort_unless($request->results()->where('status', 'approved')->exists(), 422, 'At least one approved result is required before release.');

        return DB::transaction(function () use ($request, $actor): LabRequest {
            $before = $request->toArray();
            $request->forceFill(['status' => 'released', 'released_by' => $actor->id, 'released_at' => now()])->save();
            $this->event($request, 'lab.report_released', $before['status'] ?? null, 'released', $before, $request->fresh()->toArray(), $actor);
            $this->activity->record($request->patient, 'lab.report_released', $actor, ['request_number' => $request->request_number]);

            return $request->refresh();
        });
    }

    public function acknowledgeCritical(LabResult $result, User $actor, string $notes): LabResult
    {
        abort_unless($result->is_critical, 422, 'Only critical results require critical acknowledgement.');
        $before = $result->toArray();
        $result->forceFill(['critical_acknowledged_by' => $actor->id, 'critical_acknowledged_at' => now(), 'critical_escalation_notes' => $notes])->save();
        $this->event($result, 'lab.critical_acknowledged', $result->status, $result->status, $before, $result->fresh()->toArray(), $actor, $notes);

        return $result->refresh();
    }

    public function amendReport(LabRequest $request, array $data, User $actor): LabReportAmendment
    {
        abort_unless(in_array($request->status, ['approved', 'released'], true), 422, 'Only approved or released reports can be amended.');

        $amendment = LabReportAmendment::create($data + [
            'hospital_id' => $request->hospital_id,
            'lab_request_id' => $request->id,
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ]);
        $this->event($request, 'lab.report_amended', $request->status, 'amended', null, $amendment->toArray(), $actor, $data['reason']);
        $this->activity->record($request->patient, 'lab.report_amended', $actor, ['request_number' => $request->request_number]);

        return $amendment;
    }

    private function testsFromPayload(array $data)
    {
        $testIds = collect($data['lab_test_ids'] ?? []);
        $profileTestIds = LabTestProfile::where('hospital_id', $data['hospital_id'])
            ->whereIn('id', $data['lab_test_profile_ids'] ?? [])
            ->with('tests')
            ->get()
            ->flatMap(fn (LabTestProfile $profile) => $profile->tests->pluck('id'));

        return LabTest::with(['components.unit', 'components.referenceRanges', 'billableService'])
            ->where('hospital_id', $data['hospital_id'])
            ->whereIn('id', $testIds->merge($profileTestIds)->unique()->values())
            ->where('is_active', true)
            ->get();
    }

    private function invoiceFor(LabRequest $request, Patient $patient, array $data, User $actor): Invoice
    {
        if ($request->invoice_id) {
            return $request->invoice;
        }

        return $this->invoices->createDraft([
            'facility_id' => $request->facility_id,
            'visit_id' => $request->visit_id,
            'clinical_encounter_id' => $request->clinical_encounter_id,
            'currency' => $data['currency'] ?? 'NGN',
        ], $patient, $actor);
    }

    private function resultTransition(LabResult $result, string $action, string $status, User $actor): LabResult
    {
        return DB::transaction(function () use ($result, $action, $status, $actor): LabResult {
            $result = LabResult::whereKey($result->id)->lockForUpdate()->firstOrFail();
            $before = $result->toArray();
            $from = $result->status;
            $updates = ['status' => $status];
            if ($status === 'verified') {
                $updates += ['verified_by' => $actor->id, 'verified_at' => now()];
            }
            if ($status === 'approved') {
                $updates += ['approved_by' => $actor->id, 'approved_at' => now()];
            }
            $result->forceFill($updates)->save();
            $result->requestTest->forceFill(['status' => $status])->save();
            if ($status === 'approved' && $result->request->results()->where('status', '!=', 'approved')->doesntExist()) {
                $result->request->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            } else {
                $this->transitionRequest($result->request, $action, $status, $actor);
            }
            $this->event($result, $action, $from, $status, $before, $result->fresh()->toArray(), $actor);

            return $result->refresh();
        });
    }

    private function flag(string $type, array $data, ?LabReferenceRange $range): array
    {
        if (! $range) {
            return ['normal', false];
        }
        if ($type === 'numeric' && isset($data['numeric_value'])) {
            $value = (float) $data['numeric_value'];
            $critical = ($range->critical_low_value !== null && $value < (float) $range->critical_low_value) || ($range->critical_high_value !== null && $value > (float) $range->critical_high_value);
            if ($critical) {
                return ['critical', true];
            }
            if (($range->low_value !== null && $value < (float) $range->low_value) || ($range->high_value !== null && $value > (float) $range->high_value)) {
                return ['abnormal', false];
            }
        }
        if ($type === 'qualitative' && filled($range->qualitative_normal) && ($data['qualitative_value'] ?? null) !== $range->qualitative_normal) {
            return ['abnormal', false];
        }

        return ['normal', false];
    }

    private function transitionRequest(LabRequest $request, string $action, string $status, User $actor, ?string $reason = null): void
    {
        $before = $request->toArray();
        $from = $request->status;
        $request->forceFill(['status' => $status])->save();
        $this->event($request, $action, $from, $status, $before, $request->fresh()->toArray(), $actor, $reason);
    }

    private function allocate(int $hospitalId, string $key): string
    {
        $sequence = NumberSequence::where('hospital_id', $hospitalId)->whereNull('facility_id')->where('key', $key)->where('status', 'active')->firstOrFail();

        return $this->numbers->allocate($sequence);
    }

    private function event(Model $subject, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        LabEvent::create([
            'hospital_id' => $subject->hospital_id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
