<?php

namespace App\Services;

use App\Models\BloodBankEvent;
use App\Models\BloodCompatibilityTest;
use App\Models\BloodComponent;
use App\Models\BloodComponentIssue;
use App\Models\BloodComponentReservation;
use App\Models\BloodRequest;
use App\Models\BloodRequestSpecimen;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\PatientBloodGroup;
use App\Models\PatientBloodGroupAmendment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BloodRequestWorkflowService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly AuditService $audit,
        private readonly PatientActivity $activity,
    ) {}

    public function create(array $data, User $actor): BloodRequest
    {
        return DB::transaction(function () use ($data, $actor): BloodRequest {
            $request = BloodRequest::create($data + [
                'request_number' => $this->allocate($data['hospital_id'], 'blood_request_number'),
                'quantity_reserved' => 0,
                'quantity_issued' => 0,
                'state' => 'draft',
                'created_by' => $actor->id,
            ]);
            $this->event($request, 'blood_requests.created', null, $request->toArray(), $actor);
            $this->activity->record($request->patient, 'blood_request_created', $actor, ['blood_request_id' => $request->id, 'request_number' => $request->request_number]);

            return $request->refresh();
        });
    }

    public function transition(BloodRequest $request, string $state, User $actor, string $reason): BloodRequest
    {
        abort_unless(in_array($state, ['submitted', 'accepted', 'cancelled', 'rejected'], true), 422);
        abort_if($request->hasUnresolvedDiscrepancy() && ! in_array($state, ['cancelled', 'rejected'], true), 422, 'Unresolved identity, specimen-label or blood-group discrepancy must be resolved before proceeding.');

        return DB::transaction(function () use ($request, $state, $actor, $reason): BloodRequest {
            $request = BloodRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $before = $request->toArray();
            $updates = ['state' => $state, 'status_reason' => $reason];
            if ($state === 'submitted') {
                $updates['requested_at'] = now();
            }
            if ($state === 'accepted') {
                $updates += ['accepted_by' => $actor->id, 'accepted_at' => now()];
            }
            if ($state === 'cancelled') {
                $updates += ['cancelled_by' => $actor->id, 'cancelled_at' => now()];
                $this->releaseReservations($request, $actor, 'Request cancelled: '.$reason);
            }
            if ($state === 'rejected') {
                $updates += ['rejected_by' => $actor->id, 'rejected_at' => now()];
                $this->releaseReservations($request, $actor, 'Request rejected: '.$reason);
            }
            $request->forceFill($updates)->save();
            $this->event($request, "blood_requests.{$state}", $before, $request->fresh()->toArray(), $actor, $reason, $before['state'] ?? null, $state);
            $this->activity->record($request->patient, "blood_request_{$state}", $actor, ['blood_request_id' => $request->id, 'reason' => $reason]);

            return $request->refresh();
        });
    }

    public function collectSpecimen(BloodRequest $request, array $data, User $actor): BloodRequestSpecimen
    {
        abort_if($request->state === 'cancelled' || $request->state === 'rejected', 422, 'Cancelled or rejected requests cannot receive specimens.');

        return DB::transaction(function () use ($request, $data, $actor): BloodRequestSpecimen {
            $request = BloodRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $labelStatus = $data['label_status'] ?? 'matched';
            $specimen = BloodRequestSpecimen::create([
                'hospital_id' => $request->hospital_id,
                'blood_request_id' => $request->id,
                'label' => $this->allocate($request->hospital_id, 'blood_specimen_label'),
                'collected_at' => $data['collected_at'] ?? now(),
                'collected_by' => $actor->id,
                'collection_location' => $data['collection_location'] ?? null,
                'patient_confirmed_name' => $data['patient_confirmed_name'],
                'patient_confirmed_identifier' => $data['patient_confirmed_identifier'],
                'label_status' => $labelStatus,
                'label_discrepancy_notes' => $data['label_discrepancy_notes'] ?? null,
                'status' => 'collected',
                'custody_chain' => [[
                    'action' => 'collected',
                    'actor_id' => $actor->id,
                    'at' => now()->toISOString(),
                    'location' => $data['collection_location'] ?? null,
                ]],
                'notes' => $data['notes'] ?? null,
            ]);

            $before = $request->toArray();
            $request->forceFill([
                'state' => $labelStatus === 'matched' ? 'testing' : 'specimen-required',
                'specimen_label_discrepancy_unresolved' => $labelStatus !== 'matched',
                'status_reason' => $labelStatus === 'matched' ? null : ($data['label_discrepancy_notes'] ?? 'Specimen label discrepancy recorded.'),
            ])->save();
            $this->event($specimen, 'blood_requests.specimen_collected', null, $specimen->toArray(), $actor);
            $this->event($request, 'blood_requests.specimen_status_updated', $before, $request->fresh()->toArray(), $actor);

            return $specimen;
        });
    }

    public function enterPatientGroup(BloodRequest $request, array $data, User $actor): PatientBloodGroup
    {
        $this->guardOpenAndClean($request);
        abort_unless($request->specimens()->where('status', 'collected')->exists(), 422, 'A collected patient specimen is required before group entry.');

        $existing = PatientBloodGroup::where('patient_id', $request->patient_id)->where('status', 'verified')->latest('verified_at')->first();
        if ($existing && ($existing->abo_group !== $data['abo_group'] || $existing->rh_factor !== $data['rh_factor'])) {
            $this->markDiscrepancy($request, 'blood_group_discrepancy_unresolved', 'Blood group discrepancy recorded.');
            abort(422, 'Blood group discrepancy recorded and must be resolved before continuing.');
        }

        $group = PatientBloodGroup::create([
            'hospital_id' => $request->hospital_id,
            'patient_id' => $request->patient_id,
            'blood_request_specimen_id' => $data['blood_request_specimen_id'] ?? $request->specimens()->latest()->value('id'),
            'abo_group' => $data['abo_group'],
            'rh_factor' => $data['rh_factor'],
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'entered_by' => $actor->id,
            'entered_at' => now(),
        ]);
        $this->event($group, 'blood_requests.patient_group_entered', null, $group->toArray(), $actor);

        return $group;
    }

    public function verifyPatientGroup(PatientBloodGroup $group, User $actor): PatientBloodGroup
    {
        abort_unless($group->status === 'draft', 422, 'Only draft patient blood groups can be verified.');
        abort_if($group->entered_by === $actor->id, 403, 'Patient blood group verifier must be separate from entry user.');
        $before = $group->toArray();
        $group->forceFill(['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now()])->save();
        $this->event($group, 'blood_requests.patient_group_verified', $before, $group->fresh()->toArray(), $actor);

        return $group->refresh();
    }

    public function amendPatientGroup(Patient $patient, array $data, User $actor): PatientBloodGroupAmendment
    {
        $latest = $patient->bloodGroups()->where('status', 'verified')->latest('verified_at')->first();
        $amendment = PatientBloodGroupAmendment::create([
            'hospital_id' => $patient->hospital_id,
            'patient_id' => $patient->id,
            'patient_blood_group_id' => $latest?->id,
            'abo_group' => $data['abo_group'] ?? null,
            'rh_factor' => $data['rh_factor'] ?? null,
            'reason' => $data['reason'],
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ]);
        $this->event($amendment, 'blood_requests.patient_group_amended', null, $amendment->toArray(), $actor, $data['reason']);

        return $amendment;
    }

    public function enterCompatibility(BloodRequest $request, array $data, User $actor): BloodCompatibilityTest
    {
        $this->guardOpenAndClean($request);
        abort_unless($request->specimens()->where('status', 'collected')->exists(), 422, 'A collected patient specimen is required before compatibility testing.');

        $component = isset($data['blood_component_id']) ? BloodComponent::where('hospital_id', $request->hospital_id)->findOrFail($data['blood_component_id']) : null;
        if ($component) {
            abort_unless($component->blood_component_type_id === $request->blood_component_type_id, 422, 'Selected component type does not match the request.');
        }

        $test = BloodCompatibilityTest::create([
            'hospital_id' => $request->hospital_id,
            'blood_request_id' => $request->id,
            'blood_request_specimen_id' => $data['blood_request_specimen_id'] ?? $request->specimens()->latest()->value('id'),
            'blood_component_id' => $component?->id,
            'test_type' => $data['test_type'] ?? 'manual_crossmatch',
            'result' => $data['result'],
            'interpretation' => $data['interpretation'] ?? null,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'entered_by' => $actor->id,
            'entered_at' => now(),
        ]);
        $request->forceFill(['state' => 'testing'])->save();
        $this->event($test, 'blood_requests.compatibility_entered', null, $test->toArray(), $actor);

        return $test;
    }

    public function authorizeCompatibility(BloodCompatibilityTest $test, User $actor): BloodCompatibilityTest
    {
        abort_unless($test->status === 'draft', 422, 'Only draft compatibility tests can be authorized.');
        abort_if($test->entered_by === $actor->id, 403, 'Compatibility test authorizer must be separate from entry user.');
        $before = $test->toArray();
        $test->forceFill(['status' => 'authorized', 'authorized_by' => $actor->id, 'authorized_at' => now()])->save();
        $this->event($test, 'blood_requests.compatibility_authorized', $before, $test->fresh()->toArray(), $actor);

        return $test->refresh();
    }

    public function reserve(BloodRequest $request, BloodComponent $component, User $actor, ?int $minutes = null): BloodComponentReservation
    {
        $this->guardOpenAndClean($request);

        return DB::transaction(function () use ($request, $component, $actor, $minutes): BloodComponentReservation {
            $request = BloodRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $component = BloodComponent::whereKey($component->id)->lockForUpdate()->firstOrFail();
            abort_unless($component->hospital_id === $request->hospital_id, 403);
            abort_unless($component->blood_component_type_id === $request->blood_component_type_id, 422, 'Selected component type does not match the request.');
            abort_unless($component->isUsableCandidate(), 422, 'Only available, released, non-expired and non-recalled stock can be reserved.');
            abort_unless($request->quantity_reserved < $request->quantity_requested, 422, 'Requested quantity is already fully reserved.');

            $reservation = BloodComponentReservation::create([
                'hospital_id' => $request->hospital_id,
                'blood_request_id' => $request->id,
                'blood_component_id' => $component->id,
                'status' => 'active',
                'reserved_at' => now(),
                'expires_at' => now()->addMinutes($minutes ?? $this->reservationMinutes($request->hospital_id)),
                'reserved_by' => $actor->id,
            ]);
            $component->forceFill(['state' => 'reserved'])->save();
            $request->forceFill(['quantity_reserved' => $request->quantity_reserved + 1, 'state' => 'ready'])->save();
            $this->event($reservation, 'blood_requests.component_reserved', null, $reservation->toArray(), $actor);

            return $reservation;
        });
    }

    public function expireReservations(?int $hospitalId = null): int
    {
        $count = 0;
        BloodComponentReservation::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->when($hospitalId, fn ($query) => $query->where('hospital_id', $hospitalId))
            ->with(['request', 'component'])
            ->chunkById(50, function ($reservations) use (&$count): void {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation, &$count): void {
                        $locked = BloodComponentReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
                        if ($locked->status !== 'active' || $locked->expires_at->isFuture()) {
                            return;
                        }
                        $locked->forceFill(['status' => 'expired', 'released_at' => now(), 'release_reason' => 'Reservation expired.'])->save();
                        BloodComponent::whereKey($locked->blood_component_id)->where('state', 'reserved')->update(['state' => 'available']);
                        BloodRequest::whereKey($locked->blood_request_id)->decrement('quantity_reserved');
                        $count++;
                    });
                }
            });

        return $count;
    }

    public function issue(BloodRequest $request, BloodComponentReservation $reservation, array $data, User $actor): BloodComponentIssue
    {
        $this->guardOpenAndClean($request, allowEmergency: true);

        return DB::transaction(function () use ($request, $reservation, $data, $actor): BloodComponentIssue {
            $request = BloodRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $reservation = BloodComponentReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $component = BloodComponent::whereKey($reservation->blood_component_id)->lockForUpdate()->firstOrFail();
            abort_unless($reservation->blood_request_id === $request->id && $reservation->status === 'active', 422, 'Only active reservations for this request can be issued.');
            abort_if($reservation->expires_at->isPast(), 422, 'Expired reservations cannot be issued.');
            abort_unless($component->state === 'reserved', 422, 'Only reserved components can be issued.');
            abort_unless($request->emergency_release_authorized || $this->authorizedCompatibilityExists($request, $component), 422, 'Manual authorized compatibility result or emergency-release authorization is required before issue.');
            abort_unless($request->quantity_issued < $request->quantity_requested, 422, 'Requested quantity is already fully issued.');

            $issue = BloodComponentIssue::create([
                'hospital_id' => $request->hospital_id,
                'blood_request_id' => $request->id,
                'blood_component_reservation_id' => $reservation->id,
                'blood_component_id' => $component->id,
                'issue_number' => $this->allocate($request->hospital_id, 'blood_issue_number'),
                'patient_id' => $request->patient_id,
                'issued_by' => $actor->id,
                'received_by_name' => $data['received_by_name'],
                'receiver_role' => $data['receiver_role'] ?? null,
                'issued_at' => $data['issued_at'] ?? now(),
                'destination' => $data['destination'],
                'status' => 'issued',
            ]);
            $reservation->forceFill(['status' => 'issued'])->save();
            $component->forceFill(['state' => 'issued'])->save();
            $issued = $request->quantity_issued + 1;
            $request->forceFill([
                'quantity_issued' => $issued,
                'quantity_reserved' => max(0, $request->quantity_reserved - 1),
                'state' => $issued >= $request->quantity_requested ? 'issued' : 'partially-issued',
            ])->save();
            $this->event($issue, 'blood_requests.component_issued', null, $issue->toArray(), $actor);
            $this->activity->record($request->patient, 'blood_component_issued', $actor, ['blood_request_id' => $request->id, 'issue_number' => $issue->issue_number]);

            return $issue;
        });
    }

    public function returnToStock(BloodComponentIssue $issue, array $data, User $actor): BloodComponentIssue
    {
        return DB::transaction(function () use ($issue, $data, $actor): BloodComponentIssue {
            $issue = BloodComponentIssue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            abort_unless($issue->status === 'issued', 422, 'Only issued components can be returned.');
            $before = $issue->toArray();
            $issue->forceFill([
                'status' => 'returned',
                'returned_at' => now(),
                'returned_by' => $actor->id,
                'return_reason' => $data['return_reason'],
                'return_assessed_by' => $actor->id,
                'return_assessed_at' => now(),
                'return_assessment' => $data['return_assessment'],
            ])->save();
            BloodComponent::whereKey($issue->blood_component_id)->update(['state' => 'available']);
            BloodRequest::whereKey($issue->blood_request_id)->decrement('quantity_issued');
            $this->event($issue, 'blood_requests.component_returned_to_stock', $before, $issue->fresh()->toArray(), $actor, $data['return_reason']);

            return $issue->refresh();
        });
    }

    public function reverseIssue(BloodComponentIssue $issue, string $reason, User $actor): BloodComponentIssue
    {
        return DB::transaction(function () use ($issue, $reason, $actor): BloodComponentIssue {
            $issue = BloodComponentIssue::whereKey($issue->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($issue->status, ['issued', 'returned'], true), 422, 'Only issued or returned records can be reversed.');
            $before = $issue->toArray();
            $issue->forceFill(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => $reason])->save();
            BloodComponent::whereKey($issue->blood_component_id)->update(['state' => 'available']);
            if ($before['status'] === 'issued') {
                BloodRequest::whereKey($issue->blood_request_id)->decrement('quantity_issued');
            }
            $this->event($issue, 'blood_requests.issue_reversed', $before, $issue->fresh()->toArray(), $actor, $reason);

            return $issue->refresh();
        });
    }

    public function authorizeEmergencyRelease(BloodRequest $request, string $justification, User $actor): BloodRequest
    {
        $this->guardOpenAndClean($request);
        $before = $request->toArray();
        $request->forceFill([
            'emergency_release_authorized' => true,
            'emergency_release_justification' => $justification,
            'emergency_release_authorized_by' => $actor->id,
            'emergency_release_authorized_at' => now(),
        ])->save();
        $this->event($request, 'blood_requests.emergency_release_authorized', $before, $request->fresh()->toArray(), $actor, $justification);

        return $request->refresh();
    }

    private function guardOpenAndClean(BloodRequest $request, bool $allowEmergency = false): void
    {
        abort_if(in_array($request->state, ['cancelled', 'rejected'], true), 422, 'Cancelled or rejected requests cannot proceed.');
        abort_if($request->hasUnresolvedDiscrepancy(), 422, 'Unresolved identity, specimen-label or blood-group discrepancy must be resolved before proceeding.');
        abort_if($allowEmergency === false && $request->emergency_release_authorized && blank($request->emergency_release_justification), 422, 'Emergency release requires justification.');
    }

    private function markDiscrepancy(BloodRequest $request, string $field, string $reason): void
    {
        $before = $request->toArray();
        $request->forceFill([$field => true, 'state' => 'specimen-required', 'status_reason' => $reason])->save();
        $this->event($request, 'blood_requests.discrepancy_recorded', $before, $request->fresh()->toArray(), request()->user(), $reason);
    }

    private function authorizedCompatibilityExists(BloodRequest $request, BloodComponent $component): bool
    {
        return $request->compatibilityTests()
            ->where('blood_component_id', $component->id)
            ->where('status', 'authorized')
            ->exists();
    }

    private function releaseReservations(BloodRequest $request, User $actor, string $reason): void
    {
        foreach ($request->reservations()->where('status', 'active')->lockForUpdate()->get() as $reservation) {
            $reservation->forceFill(['status' => 'released', 'released_at' => now(), 'released_by' => $actor->id, 'release_reason' => $reason])->save();
            BloodComponent::whereKey($reservation->blood_component_id)->where('state', 'reserved')->update(['state' => 'available']);
        }
        $request->forceFill(['quantity_reserved' => 0])->save();
    }

    private function reservationMinutes(int $hospitalId): int
    {
        $setting = HospitalSetting::where('hospital_id', $hospitalId)->first();

        return (int) data_get($setting?->operating_preferences, 'blood_bank.reservation_expiry_minutes', 60);
    }

    private function allocate(int $hospitalId, string $key): string
    {
        $sequence = NumberSequence::where('hospital_id', $hospitalId)->whereNull('facility_id')->where('key', $key)->where('status', 'active')->firstOrFail();

        return $this->numbers->allocate($sequence);
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, ?User $actor, ?string $reason = null, ?string $from = null, ?string $to = null): void
    {
        BloodBankEvent::create([
            'hospital_id' => $subject->hospital_id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'actor_id' => $actor?->id,
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
