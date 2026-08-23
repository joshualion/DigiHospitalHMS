<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncounterVital extends Model
{
    protected $fillable = ['clinical_encounter_id', 'hospital_id', 'patient_id', 'temperature', 'temperature_unit', 'pulse', 'respiratory_rate', 'blood_pressure_systolic', 'blood_pressure_diastolic', 'oxygen_saturation', 'weight_kg', 'height_cm', 'bmi', 'pain_score', 'measured_at', 'recorded_by', 'notes'];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime', 'temperature' => 'decimal:2', 'weight_kg' => 'decimal:2', 'height_cm' => 'decimal:2', 'bmi' => 'decimal:2'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
