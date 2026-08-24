<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InpatientChart extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'admission_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'department_id', 'ward_id', 'bed_id', 'status', 'opened_by', 'opened_at', 'closed_by', 'closed_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function progressNotes(): HasMany
    {
        return $this->hasMany(InpatientProgressNote::class);
    }

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(InpatientNursingNote::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(InpatientObservation::class);
    }

    public function intakeOutputs(): HasMany
    {
        return $this->hasMany(InpatientIntakeOutput::class);
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(InpatientCarePlan::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(InpatientDiagnosis::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(InpatientOrder::class);
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(InpatientHandoverRecord::class);
    }

    public function dischargeSummary(): HasOne
    {
        return $this->hasOne(InpatientDischargeSummary::class);
    }

    public function emarSchedules(): HasMany
    {
        return $this->hasMany(EmarSchedule::class);
    }

    public function emarAdministrations(): HasMany
    {
        return $this->hasMany(EmarAdministration::class);
    }
}
