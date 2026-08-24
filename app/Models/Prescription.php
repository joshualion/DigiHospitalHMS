<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'patient_id', 'clinical_encounter_id', 'prescribing_clinician_id', 'invoice_id', 'prescription_number', 'status', 'clinical_note', 'created_by', 'signed_by', 'signed_at', 'status_reason', 'completed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PrescriptionReview::class);
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(PrescriptionDispense::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(PrescriptionAmendment::class);
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }
}
