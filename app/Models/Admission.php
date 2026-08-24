<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Admission extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'requesting_clinician_id', 'attending_clinician_id', 'department_id', 'current_ward_id', 'current_bed_id', 'invoice_id', 'admission_number', 'status', 'reason', 'provisional_diagnosis', 'notes', 'requested_at', 'requested_by', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'admitted_at', 'discharged_at', 'discharge_destination', 'discharge_outcome', 'discharge_notes', 'administrative_clearance_required', 'administrative_clearance_resolved', 'discharge_override_used', 'status_reason'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'admitted_at' => 'datetime', 'discharged_at' => 'datetime', 'administrative_clearance_required' => 'boolean', 'administrative_clearance_resolved' => 'boolean', 'discharge_override_used' => 'boolean'];
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

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'current_ward_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'current_bed_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AdmissionBedMovement::class);
    }

    public function chart(): HasOne
    {
        return $this->hasOne(InpatientChart::class);
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
