<?php

namespace App\Services;

use App\Models\AppointmentEvent;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEncounterEvent;
use App\Models\EncounterAmendment;
use App\Models\EncounterDiagnosis;
use App\Models\EncounterVital;
use App\Models\QueueEvent;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class ClinicalEncounterWorkflowService
{
    public function __construct(private readonly AuditService $audit) {}

    public function start(Visit $visit, User $actor): ClinicalEncounter
    {
        abort_unless($actor->staffProfile, 422, 'A staff profile is required to start an encounter.');

        return DB::transaction(function () use ($visit, $actor): ClinicalEncounter {
            $visit = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            $activeExists = ClinicalEncounter::where('visit_id', $visit->id)
                ->whereIn('status', ['in_progress', 'paused'])
                ->lockForUpdate()
                ->exists();
            abort_if($activeExists, 422, 'This visit already has an active encounter.');

            $queue = $visit->queueEntry;
            $encounter = ClinicalEncounter::create([
                'hospital_id' => $visit->hospital_id,
                'facility_id' => $visit->facility_id,
                'department_id' => $visit->department_id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'appointment_id' => $visit->appointment_id,
                'queue_entry_id' => $queue?->id,
                'responsible_clinician_id' => $visit->clinician_id ?? $actor->staffProfile->id,
                'source' => $visit->source,
                'status' => 'in_progress',
                'started_by' => $actor->id,
                'started_at' => now(),
            ]);

            $visit->forceFill(['status' => 'in_encounter'])->save();
            if ($queue && in_array($queue->status, ['waiting', 'called', 'skipped'], true)) {
                $queue->forceFill(['status' => 'in_encounter'])->save();
            }

            $this->event($encounter, 'started', null, 'in_progress', null, $encounter->toArray(), $actor);
            $this->audit->record('encounters.started', $encounter, null, $encounter->toArray(), actor: $actor);

            return $encounter;
        });
    }

    public function transition(ClinicalEncounter $encounter, string $action, User $actor, ?string $reason = null): ClinicalEncounter
    {
        return DB::transaction(function () use ($encounter, $action, $actor, $reason): ClinicalEncounter {
            $encounter = ClinicalEncounter::query()->whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            $before = $encounter->toArray();
            $from = $encounter->status;
            $updates = match ($action) {
                'pause' => $this->assertStatus($encounter, ['in_progress'], ['status' => 'paused', 'paused_by' => $actor->id, 'paused_at' => now(), 'status_reason' => $reason]),
                'resume' => $this->assertStatus($encounter, ['paused'], ['status' => 'in_progress', 'resumed_by' => $actor->id, 'resumed_at' => now(), 'status_reason' => $reason]),
                'cancel' => $this->assertStatus($encounter, ['in_progress', 'paused'], ['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'status_reason' => $reason]),
                'sign' => $this->assertStatus($encounter, ['in_progress'], ['status' => 'signed', 'signed_by' => $actor->id, 'signed_at' => now(), 'status_reason' => $reason]),
                default => abort(422, 'Unsupported encounter transition.'),
            };

            $encounter->forceFill($updates)->save();
            if ($action === 'sign') {
                $encounter->visit()->update(['status' => 'completed']);
                if ($queue = $encounter->queueEntry) {
                    $queueBefore = $queue->toArray();
                    $queue->forceFill(['status' => 'removed', 'removed_at' => now()])->save();
                    $this->queueEvent($queue, 'encounter_signed', $queueBefore['status'] ?? null, 'removed', $queueBefore, $queue->fresh()->toArray(), $actor, $reason);
                }
                if ($appointment = $encounter->appointment) {
                    $appointmentBefore = $appointment->toArray();
                    $appointment->forceFill(['status' => 'completed'])->save();
                    $this->appointmentEvent($appointment, 'completed_from_encounter', $appointmentBefore['status'] ?? null, 'completed', $appointmentBefore, $appointment->fresh()->toArray(), $actor, $reason);
                }
            }

            if ($action === 'cancel') {
                $encounter->visit()->update(['status' => 'checked_in']);
                if ($queue = $encounter->queueEntry) {
                    $queueBefore = $queue->toArray();
                    $queue->forceFill(['status' => 'waiting'])->save();
                    $this->queueEvent($queue, 'encounter_cancelled', $queueBefore['status'] ?? null, 'waiting', $queueBefore, $queue->fresh()->toArray(), $actor, $reason);
                }
            }

            $encounter->refresh();
            $this->event($encounter, $action, $from, $encounter->status, $before, $encounter->toArray(), $actor, $reason);
            $this->audit->record("encounters.{$action}", $encounter, $before, $encounter->toArray(), actor: $actor, reason: $reason);

            return $encounter;
        });
    }

    public function recordVitals(ClinicalEncounter $encounter, array $data, User $actor): EncounterVital
    {
        abort_if($encounter->isSigned(), 422, 'Signed encounters cannot be changed. Add an amendment instead.');
        abort_if($encounter->status === 'cancelled', 422, 'Cancelled encounters cannot be changed.');

        $height = (float) ($data['height_cm'] ?? 0);
        $weight = (float) ($data['weight_kg'] ?? 0);
        $bmi = $height > 0 && $weight > 0 ? round($weight / (($height / 100) ** 2), 2) : null;

        $vital = EncounterVital::create($data + [
            'clinical_encounter_id' => $encounter->id,
            'hospital_id' => $encounter->hospital_id,
            'patient_id' => $encounter->patient_id,
            'bmi' => $bmi,
            'recorded_by' => $actor->id,
        ]);

        $this->event($encounter, 'vitals_recorded', $encounter->status, $encounter->status, null, $vital->toArray(), $actor);
        $this->audit->record('encounters.vitals_recorded', $vital, null, $vital->toArray(), actor: $actor);

        return $vital;
    }

    public function updateAssessment(ClinicalEncounter $encounter, array $data, User $actor): ClinicalEncounter
    {
        abort_if($encounter->isSigned(), 422, 'Signed encounters cannot be changed. Add an amendment instead.');
        abort_if($encounter->status === 'cancelled', 422, 'Cancelled encounters cannot be changed.');

        return DB::transaction(function () use ($encounter, $data, $actor): ClinicalEncounter {
            $before = $encounter->fresh()->toArray();
            $encounter->fill($data)->save();
            $this->event($encounter, 'assessment_updated', $encounter->status, $encounter->status, $before, $encounter->fresh()->toArray(), $actor);
            $this->audit->record('encounters.assessment_updated', $encounter, $before, $encounter->fresh()->toArray(), actor: $actor);

            return $encounter->refresh();
        });
    }

    public function addDiagnosis(ClinicalEncounter $encounter, array $data, User $actor): EncounterDiagnosis
    {
        abort_if($encounter->isSigned(), 422, 'Signed encounters cannot be changed. Add an amendment instead.');
        abort_if($encounter->status === 'cancelled', 422, 'Cancelled encounters cannot be changed.');

        $diagnosis = EncounterDiagnosis::create($data + [
            'clinical_encounter_id' => $encounter->id,
            'hospital_id' => $encounter->hospital_id,
            'recorded_by' => $actor->id,
            'recorded_at' => now(),
        ]);

        $this->event($encounter, 'diagnosis_recorded', $encounter->status, $encounter->status, null, $diagnosis->toArray(), $actor);
        $this->audit->record('encounters.diagnosis_recorded', $diagnosis, null, $diagnosis->toArray(), actor: $actor);

        return $diagnosis;
    }

    public function amend(ClinicalEncounter $encounter, array $data, User $actor): EncounterAmendment
    {
        abort_unless($encounter->isSigned(), 422, 'Only signed encounters require append-only amendments.');

        $amendment = EncounterAmendment::create($data + [
            'clinical_encounter_id' => $encounter->id,
            'hospital_id' => $encounter->hospital_id,
            'authored_by' => $actor->id,
            'authored_at' => now(),
        ]);

        $this->event($encounter, 'amended', $encounter->status, $encounter->status, null, $amendment->toArray(), $actor, $data['reason']);
        $this->audit->record('encounters.amended', $amendment, null, $amendment->toArray(), actor: $actor, reason: $data['reason']);

        return $amendment;
    }

    private function assertStatus(ClinicalEncounter $encounter, array $allowed, array $updates): array
    {
        abort_unless(in_array($encounter->status, $allowed, true), 422, 'Invalid encounter status transition.');

        return $updates;
    }

    private function event(ClinicalEncounter $encounter, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        ClinicalEncounterEvent::create([
            'clinical_encounter_id' => $encounter->id,
            'hospital_id' => $encounter->hospital_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    private function queueEvent($queue, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        QueueEvent::create([
            'queue_entry_id' => $queue->id,
            'hospital_id' => $queue->hospital_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        $this->audit->record("queues.{$action}", $queue, $before, $after, actor: $actor, reason: $reason);
    }

    private function appointmentEvent($appointment, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        AppointmentEvent::create([
            'appointment_id' => $appointment->id,
            'hospital_id' => $appointment->hospital_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        $this->audit->record("appointments.{$action}", $appointment, $before, $after, actor: $actor, reason: $reason);
    }
}
