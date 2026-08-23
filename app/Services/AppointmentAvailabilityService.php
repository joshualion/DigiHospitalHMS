<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ClinicianSchedule;
use App\Models\ClinicianUnavailability;
use App\Models\Hospital;
use App\Models\StaffProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

class AppointmentAvailabilityService
{
    public function slots(Hospital $hospital, StaffProfile $clinician, AppointmentType $type, string $date, int $facilityId): array
    {
        $day = CarbonImmutable::parse($date, $hospital->timezone);
        $schedule = ClinicianSchedule::where('hospital_id', $hospital->id)
            ->where('facility_id', $facilityId)
            ->where('staff_profile_id', $clinician->id)
            ->where('day_of_week', $day->dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (! $schedule) {
            return [];
        }

        $cursor = CarbonImmutable::parse("{$date} {$schedule->starts_at}", $hospital->timezone);
        $end = CarbonImmutable::parse("{$date} {$schedule->ends_at}", $hospital->timezone);
        $duration = max(5, (int) $type->duration_minutes);
        $slots = [];

        while ($cursor->addMinutes($duration)->lessThanOrEqualTo($end)) {
            $slotEnd = $cursor->addMinutes($duration);

            if (! $this->overlapsBreak($cursor, $slotEnd, $schedule->breaks ?? [])
                && ! $this->hasUnavailable($hospital->id, $clinician->id, $cursor, $slotEnd)
                && ! $this->hasBooked($hospital->id, $clinician->id, $cursor, $slotEnd)) {
                $slots[] = [
                    'starts_at' => $cursor->toIso8601String(),
                    'ends_at' => $slotEnd->toIso8601String(),
                    'label' => $cursor->format('H:i'),
                ];
            }

            $cursor = $slotEnd;
        }

        return $slots;
    }

    public function assertAvailable(int $hospitalId, int $clinicianId, string $startsAt, string $endsAt, ?int $ignoreAppointmentId = null): void
    {
        $startsAt = Carbon::parse($startsAt)->toDateTimeString();
        $endsAt = Carbon::parse($endsAt)->toDateTimeString();

        $conflict = Appointment::where('hospital_id', $hospitalId)
            ->where('clinician_id', $clinicianId)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();

        abort_if($conflict, 422, 'This clinician already has a booking for that time.');
    }

    private function overlapsBreak(CarbonImmutable $start, CarbonImmutable $end, array $breaks): bool
    {
        foreach ($breaks as $break) {
            if (! filled($break['starts_at'] ?? null) || ! filled($break['ends_at'] ?? null)) {
                continue;
            }

            $breakStart = CarbonImmutable::parse($start->toDateString().' '.$break['starts_at'], $start->timezone);
            $breakEnd = CarbonImmutable::parse($start->toDateString().' '.$break['ends_at'], $start->timezone);

            if ($start->lessThan($breakEnd) && $end->greaterThan($breakStart)) {
                return true;
            }
        }

        return false;
    }

    private function hasUnavailable(int $hospitalId, int $clinicianId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return ClinicianUnavailability::where('hospital_id', $hospitalId)
            ->where('staff_profile_id', $clinicianId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
    }

    private function hasBooked(int $hospitalId, int $clinicianId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return Appointment::where('hospital_id', $hospitalId)
            ->where('clinician_id', $clinicianId)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->exists();
    }
}
