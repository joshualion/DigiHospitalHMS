<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AdmissionBedMovement;
use App\Models\AdmissionEvent;
use App\Models\Bed;
use App\Models\BillableService;
use App\Models\ClinicalEncounter;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdmissionWorkflowService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly InvoiceWorkflowService $invoices,
        private readonly AuditService $audit,
        private readonly PatientActivity $activity,
    ) {}

    public function request(array $data, User $actor): Admission
    {
        $patient = Patient::where('hospital_id', $data['hospital_id'])->findOrFail($data['patient_id']);
        $visit = isset($data['visit_id']) ? Visit::where('hospital_id', $data['hospital_id'])->findOrFail($data['visit_id']) : null;
        $encounter = isset($data['clinical_encounter_id']) ? ClinicalEncounter::where('hospital_id', $data['hospital_id'])->findOrFail($data['clinical_encounter_id']) : null;
        $admission = Admission::create([
            'hospital_id' => $data['hospital_id'],
            'facility_id' => $data['facility_id'],
            'patient_id' => $patient->id,
            'visit_id' => $visit?->id,
            'clinical_encounter_id' => $encounter?->id,
            'requesting_clinician_id' => $data['requesting_clinician_id'] ?? $actor->staffProfile?->id,
            'attending_clinician_id' => $data['attending_clinician_id'] ?? null,
            'department_id' => $data['department_id'] ?? $visit?->department_id,
            'status' => 'requested',
            'reason' => $data['reason'] ?? null,
            'provisional_diagnosis' => $data['provisional_diagnosis'] ?? null,
            'notes' => $data['notes'] ?? null,
            'requested_by' => $actor->id,
            'requested_at' => now(),
            'administrative_clearance_required' => (bool) ($data['administrative_clearance_required'] ?? false),
            'administrative_clearance_resolved' => ! (bool) ($data['administrative_clearance_required'] ?? false),
        ]);
        $this->event($admission, 'admissions.requested', null, $admission->toArray(), $actor);
        $this->activity->record($patient, 'admission.requested', $actor, ['admission_id' => $admission->id]);

        return $admission->refresh();
    }

    public function approve(Admission $admission, User $actor, ?string $reason = null): Admission
    {
        abort_unless($admission->status === 'requested', 422, 'Only requested admissions can be approved.');
        $before = $admission->toArray();
        $admission->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'status_reason' => $reason])->save();
        $this->event($admission, 'admissions.approved', $before, $admission->fresh()->toArray(), $actor, $reason);

        return $admission->refresh();
    }

    public function reject(Admission $admission, User $actor, string $reason): Admission
    {
        abort_unless($admission->status === 'requested', 422, 'Only requested admissions can be rejected.');
        $before = $admission->toArray();
        $admission->forceFill(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(), 'status_reason' => $reason])->save();
        $this->event($admission, 'admissions.rejected', $before, $admission->fresh()->toArray(), $actor, $reason);

        return $admission->refresh();
    }

    public function admit(Admission $admission, Bed $bed, User $actor, array $data = []): Admission
    {
        abort_unless($admission->status === 'approved', 422, 'Only approved admissions can be admitted.');

        return DB::transaction(function () use ($admission, $bed, $actor, $data): Admission {
            $admission = Admission::whereKey($admission->id)->lockForUpdate()->firstOrFail();
            $bed = Bed::whereKey($bed->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignableBed($bed);
            abort_if(Admission::where('current_bed_id', $bed->id)->whereIn('status', ['admitted', 'transferred'])->exists(), 422, 'Bed is already occupied.');
            $before = $admission->toArray();
            $admission->forceFill([
                'status' => 'admitted',
                'admission_number' => $admission->admission_number ?: $this->allocate($admission->hospital_id),
                'attending_clinician_id' => $data['attending_clinician_id'] ?? $admission->attending_clinician_id,
                'department_id' => $data['department_id'] ?? $admission->department_id,
                'current_ward_id' => $bed->ward_id,
                'current_bed_id' => $bed->id,
                'admitted_at' => $data['admitted_at'] ?? now(),
                'notes' => $data['notes'] ?? $admission->notes,
            ])->save();
            $bed->forceFill(['state' => 'occupied', 'state_reason' => 'Admission '.$admission->admission_number])->save();
            $this->movement($admission, null, $bed, 'admit', $actor, $data['reason'] ?? null, $admission->admitted_at);
            $this->event($admission, 'admissions.admitted', $before, $admission->fresh()->toArray(), $actor, $data['reason'] ?? null);
            $this->activity->record($admission->patient, 'admission.admitted', $actor, ['admission_id' => $admission->id, 'bed_id' => $bed->id]);

            return $admission->refresh();
        });
    }

    public function transfer(Admission $admission, Bed $toBed, User $actor, string $reason): Admission
    {
        abort_unless(in_array($admission->status, ['admitted', 'transferred'], true), 422, 'Only active admissions can be transferred.');

        return DB::transaction(function () use ($admission, $toBed, $actor, $reason): Admission {
            $admission = Admission::whereKey($admission->id)->lockForUpdate()->firstOrFail();
            $fromBed = Bed::whereKey($admission->current_bed_id)->lockForUpdate()->firstOrFail();
            $toBed = Bed::whereKey($toBed->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignableBed($toBed);
            abort_if($fromBed->id === $toBed->id, 422, 'Transfer bed must be different.');
            abort_if(Admission::where('current_bed_id', $toBed->id)->whereIn('status', ['admitted', 'transferred'])->exists(), 422, 'Bed is already occupied.');
            $this->closeOpenMovement($admission, now());
            $before = $admission->toArray();
            $fromBed->forceFill(['state' => 'cleaning', 'state_reason' => 'Patient transferred'])->save();
            $toBed->forceFill(['state' => 'occupied', 'state_reason' => 'Admission transfer '.$admission->admission_number])->save();
            $admission->forceFill(['status' => 'transferred', 'facility_id' => $toBed->facility_id, 'current_ward_id' => $toBed->ward_id, 'current_bed_id' => $toBed->id])->save();
            $this->movement($admission, $fromBed, $toBed, 'transfer', $actor, $reason, now());
            $this->event($admission, 'admissions.transferred', $before, $admission->fresh()->toArray(), $actor, $reason);
            $this->activity->record($admission->patient, 'admission.transferred', $actor, ['admission_id' => $admission->id, 'from_bed_id' => $fromBed->id, 'to_bed_id' => $toBed->id]);

            return $admission->refresh();
        });
    }

    public function discharge(Admission $admission, User $actor, array $data): Admission
    {
        abort_unless(in_array($admission->status, ['admitted', 'transferred'], true), 422, 'Only active admissions can be discharged.');
        $override = (bool) ($data['override'] ?? false);
        if ($admission->administrative_clearance_required && ! $admission->administrative_clearance_resolved) {
            abort_unless($override && $actor->can('admissions.discharge.override'), 403, 'Administrative clearance is unresolved.');
            abort_unless(filled($data['override_reason'] ?? null), 422, 'Override reason is required.');
        }

        return DB::transaction(function () use ($admission, $actor, $data, $override): Admission {
            $admission = Admission::whereKey($admission->id)->lockForUpdate()->firstOrFail();
            $bed = Bed::whereKey($admission->current_bed_id)->lockForUpdate()->firstOrFail();
            $this->closeOpenMovement($admission, $data['discharged_at'] ?? now());
            $invoice = $this->chargeAccommodation($admission, $actor, $data['discharged_at'] ?? now());
            $before = $admission->toArray();
            $admission->forceFill([
                'status' => 'discharged',
                'invoice_id' => $invoice?->id ?? $admission->invoice_id,
                'discharged_at' => $data['discharged_at'] ?? now(),
                'discharge_destination' => $data['discharge_destination'] ?? null,
                'discharge_outcome' => $data['discharge_outcome'] ?? null,
                'discharge_notes' => $data['discharge_notes'] ?? null,
                'discharge_override_used' => $override,
                'status_reason' => $data['override_reason'] ?? $data['reason'] ?? null,
            ])->save();
            $bed->forceFill(['state' => 'cleaning', 'state_reason' => 'Discharged patient'])->save();
            $this->event($admission, 'admissions.discharged', $before, $admission->fresh()->toArray(), $actor, $data['override_reason'] ?? null);
            $this->activity->record($admission->patient, 'admission.discharged', $actor, ['admission_id' => $admission->id]);

            return $admission->refresh();
        });
    }

    public function cancel(Admission $admission, User $actor, string $reason): Admission
    {
        abort_unless(in_array($admission->status, ['requested', 'approved'], true), 422, 'Only requested or approved admissions can be cancelled.');
        $before = $admission->toArray();
        $admission->forceFill(['status' => 'cancelled', 'status_reason' => $reason])->save();
        $this->event($admission, 'admissions.cancelled', $before, $admission->fresh()->toArray(), $actor, $reason);

        return $admission->refresh();
    }

    public function setBedState(Bed $bed, string $state, User $actor, string $reason): Bed
    {
        abort_unless(in_array($state, ['available', 'reserved', 'cleaning', 'maintenance', 'blocked', 'inactive'], true), 422, 'Invalid bed state.');
        abort_if($bed->state === 'occupied', 422, 'Occupied beds cannot be manually changed.');
        $before = $bed->toArray();
        $bed->forceFill(['state' => $state, 'state_reason' => $reason])->save();
        AdmissionBedMovement::create(['hospital_id' => $bed->hospital_id, 'to_facility_id' => $bed->facility_id, 'to_ward_id' => $bed->ward_id, 'to_bed_id' => $bed->id, 'movement_type' => "bed_{$state}", 'started_at' => now(), 'reason' => $reason, 'performed_by' => $actor->id]);
        $this->event($bed, 'beds.state_changed', $before, $bed->fresh()->toArray(), $actor, $reason);

        return $bed->refresh();
    }

    private function chargeAccommodation(Admission $admission, User $actor, mixed $endedAt): ?Invoice
    {
        $movements = $admission->movements()->with('toBed.bedClass.billableService')->whereIn('movement_type', ['admit', 'transfer'])->get();
        $billableMovements = $movements->filter(fn (AdmissionBedMovement $movement): bool => $movement->toBed?->bedClass?->billableService instanceof BillableService);
        if ($billableMovements->isEmpty()) {
            return null;
        }
        $invoice = $admission->invoice ?: $this->invoices->createDraft(['facility_id' => $admission->facility_id, 'visit_id' => $admission->visit_id, 'clinical_encounter_id' => $admission->clinical_encounter_id, 'currency' => $admission->patient->hospital?->default_currency ?? 'NGN'], $admission->patient, $actor);
        foreach ($billableMovements as $movement) {
            $start = $movement->started_at;
            $end = $movement->ended_at ?? $endedAt;
            $days = max(1, (int) ceil($start->diffInHours($end) / 24));
            $this->invoices->addServiceLine($invoice->fresh(), $movement->toBed->bedClass->billableService, ['quantity' => $days], $actor);
        }

        return $invoice->refresh();
    }

    private function assertAssignableBed(Bed $bed): void
    {
        abort_unless($bed->state === 'available' || $bed->state === 'reserved', 422, 'Bed is not available for allocation.');
    }

    private function closeOpenMovement(Admission $admission, mixed $endedAt): void
    {
        AdmissionBedMovement::where('admission_id', $admission->id)->whereNull('ended_at')->latest()->first()?->forceFill(['ended_at' => $endedAt])->save();
    }

    private function movement(Admission $admission, ?Bed $fromBed, Bed $toBed, string $type, User $actor, ?string $reason, mixed $startedAt): AdmissionBedMovement
    {
        return AdmissionBedMovement::create(['hospital_id' => $admission->hospital_id, 'admission_id' => $admission->id, 'from_facility_id' => $fromBed?->facility_id, 'to_facility_id' => $toBed->facility_id, 'from_department_id' => $admission->department_id, 'to_department_id' => $admission->department_id, 'from_ward_id' => $fromBed?->ward_id, 'to_ward_id' => $toBed->ward_id, 'from_bed_id' => $fromBed?->id, 'to_bed_id' => $toBed->id, 'movement_type' => $type, 'started_at' => $startedAt, 'reason' => $reason, 'performed_by' => $actor->id]);
    }

    private function allocate(int $hospitalId): string
    {
        return $this->numbers->allocate(NumberSequence::where('hospital_id', $hospitalId)->whereNull('facility_id')->where('key', 'admission_number')->where('status', 'active')->firstOrFail());
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        AdmissionEvent::create(['hospital_id' => $subject->hospital_id, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'actor_id' => $actor->id, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
