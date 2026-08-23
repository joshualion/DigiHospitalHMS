<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientActivityEvent;
use App\Models\User;

class PatientActivity
{
    public function record(Patient $patient, string $action, ?User $actor = null, array $metadata = []): PatientActivityEvent
    {
        return PatientActivityEvent::create([
            'patient_id' => $patient->id,
            'hospital_id' => $patient->hospital_id,
            'actor_id' => $actor?->id ?? request()->user()?->id,
            'action' => $action,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
