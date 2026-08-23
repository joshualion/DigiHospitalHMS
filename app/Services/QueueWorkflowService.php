<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\QueueEvent;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class QueueWorkflowService
{
    public function __construct(private readonly AuditService $audit) {}

    public function checkIn(Patient $patient, array $data, User $actor, ?Appointment $appointment = null): QueueEntry
    {
        abort_if(in_array($patient->status, ['archived', 'deceased'], true), 422, 'Archived or deceased patients cannot be checked in in Phase 2B.');

        return DB::transaction(function () use ($patient, $data, $actor, $appointment): QueueEntry {
            if ($appointment) {
                $appointment->forceFill(['status' => 'checked_in'])->save();
            }

            $visit = Visit::create([
                'hospital_id' => $patient->hospital_id,
                'facility_id' => $data['facility_id'],
                'department_id' => $data['department_id'] ?? null,
                'patient_id' => $patient->id,
                'clinician_id' => $data['clinician_id'] ?? $appointment?->clinician_id,
                'appointment_id' => $appointment?->id,
                'source' => $appointment ? 'appointment' : 'walk_in',
                'status' => 'checked_in',
                'checked_in_by' => $actor->id,
                'checked_in_at' => now(),
            ]);

            $queueDate = today()->toDateString();
            $nextNumber = ((int) QueueEntry::where('facility_id', $data['facility_id'])
                ->whereDate('queue_date', $queueDate)
                ->lockForUpdate()
                ->max('queue_number')) + 1;

            $queue = QueueEntry::create([
                'hospital_id' => $patient->hospital_id,
                'facility_id' => $data['facility_id'],
                'department_id' => $data['department_id'] ?? null,
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'clinician_id' => $data['clinician_id'] ?? $appointment?->clinician_id,
                'queue_date' => $queueDate,
                'queue_number' => $nextNumber,
                'priority' => $data['priority'] ?? 3,
                'status' => 'waiting',
                'created_by' => $actor->id,
            ]);

            $this->event($queue, 'checked_in', null, 'waiting', null, $queue->toArray(), $actor);
            $this->audit->record('queues.checked_in', $queue, null, $queue->toArray(), actor: $actor);

            return $queue;
        });
    }

    public function transition(QueueEntry $queue, string $action, User $actor, ?string $reason = null, array $changes = []): QueueEntry
    {
        return DB::transaction(function () use ($queue, $action, $actor, $reason, $changes): QueueEntry {
            $before = $queue->fresh()->toArray();
            $from = $queue->status;
            $updates = match ($action) {
                'call' => ['status' => 'called', 'called_at' => now()],
                'recall' => ['status' => 'called', 'called_at' => now()],
                'skip' => ['status' => 'skipped'],
                'remove' => ['status' => 'removed', 'removed_at' => now()],
                'transfer' => ['status' => 'waiting'] + $changes,
                'priority' => $changes,
                default => abort(422, 'Unsupported queue transition.'),
            };

            $queue->forceFill($updates)->save();
            $queue->refresh();
            $this->event($queue, $action, $from, $queue->status, $before, $queue->toArray(), $actor, $reason);
            $this->audit->record("queues.{$action}", $queue, $before, $queue->toArray(), actor: $actor, reason: $reason);

            return $queue;
        });
    }

    private function event(QueueEntry $queue, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
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
    }
}
