<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentWorkflowService
{
    public function __construct(private readonly AppointmentAvailabilityService $availability, private readonly AuditService $audit) {}

    public function book(array $data, Patient $patient, User $actor): Appointment
    {
        abort_if(in_array($patient->status, ['archived', 'deceased'], true), 422, 'Archived or deceased patients cannot be booked in Phase 2B.');

        return DB::transaction(function () use ($data, $patient, $actor): Appointment {
            $type = AppointmentType::findOrFail($data['appointment_type_id']);
            $startsAt = Carbon::parse($data['starts_at'])->toDateTimeString();
            $endsAt = Carbon::parse($startsAt)->addMinutes($type->duration_minutes)->toDateTimeString();
            $this->availability->assertAvailable($patient->hospital_id, (int) $data['clinician_id'], $startsAt, $endsAt);

            $appointment = Appointment::create([
                'hospital_id' => $patient->hospital_id,
                'facility_id' => $data['facility_id'],
                'department_id' => $data['department_id'] ?? null,
                'patient_id' => $patient->id,
                'clinician_id' => $data['clinician_id'],
                'appointment_type_id' => $type->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'scheduled',
                'source' => $data['source'] ?? 'staff',
                'reason' => $data['reason'] ?? null,
                'booked_by' => $actor->id,
            ]);

            $this->event($appointment, 'booked', null, 'scheduled', null, $appointment->toArray(), $actor);
            $this->audit->record('appointments.booked', $appointment, null, $appointment->toArray(), actor: $actor);

            return $appointment;
        });
    }

    public function transition(Appointment $appointment, string $action, User $actor, ?string $reason = null, array $changes = []): Appointment
    {
        return DB::transaction(function () use ($appointment, $action, $actor, $reason, $changes): Appointment {
            $before = $appointment->fresh()->toArray();
            $from = $appointment->status;
            $updates = match ($action) {
                'confirm' => ['status' => 'confirmed', 'confirmed_at' => now()],
                'cancel' => ['status' => 'cancelled', 'cancelled_at' => now(), 'reason' => $reason],
                'no_show' => ['status' => 'no_show', 'no_show_at' => now(), 'reason' => $reason],
                'reschedule' => $changes + ['status' => 'scheduled', 'reason' => $reason],
                default => abort(422, 'Unsupported appointment transition.'),
            };

            if ($action === 'reschedule') {
                $this->availability->assertAvailable($appointment->hospital_id, $appointment->clinician_id, $updates['starts_at'], $updates['ends_at'], $appointment->id);
            }

            $appointment->forceFill($updates)->save();
            $appointment->refresh();
            $this->event($appointment, $action, $from, $appointment->status, $before, $appointment->toArray(), $actor, $reason);
            $this->audit->record("appointments.{$action}", $appointment, $before, $appointment->toArray(), actor: $actor, reason: $reason);

            return $appointment;
        });
    }

    private function event(Appointment $appointment, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
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
    }
}
