<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InpatientObservation extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'temperature', 'temperature_unit', 'pulse', 'respiratory_rate', 'blood_pressure_systolic', 'blood_pressure_diastolic', 'oxygen_saturation', 'pain_score', 'glucose', 'glucose_unit', 'consciousness_notes', 'observed_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['observed_at' => 'datetime'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }
}
