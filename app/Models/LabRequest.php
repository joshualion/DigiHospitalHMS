<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabRequest extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'department_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'ordering_clinician_id', 'ordered_by', 'request_number', 'accession_number', 'status', 'priority', 'clinical_notes', 'invoice_id', 'ordered_at', 'approved_at', 'approved_by', 'released_at', 'released_by'];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime', 'approved_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabRequestTest::class);
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(LabSpecimen::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(LabReportAmendment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
