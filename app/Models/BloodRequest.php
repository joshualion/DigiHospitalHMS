<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BloodRequest extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'patient_id', 'clinical_encounter_id', 'admission_id', 'requesting_clinician_id', 'blood_component_type_id', 'request_number', 'quantity_requested', 'quantity_reserved', 'quantity_issued', 'clinical_indication', 'priority', 'required_at', 'state', 'identity_discrepancy_unresolved', 'specimen_label_discrepancy_unresolved', 'blood_group_discrepancy_unresolved', 'emergency_release_authorized', 'emergency_release_justification', 'emergency_release_authorized_by', 'emergency_release_authorized_at', 'created_by', 'requested_at', 'accepted_by', 'accepted_at', 'cancelled_by', 'cancelled_at', 'rejected_by', 'rejected_at', 'status_reason'];

    protected function casts(): array
    {
        return [
            'required_at' => 'datetime',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
            'emergency_release_authorized_at' => 'datetime',
            'identity_discrepancy_unresolved' => 'boolean',
            'specimen_label_discrepancy_unresolved' => 'boolean',
            'blood_group_discrepancy_unresolved' => 'boolean',
            'emergency_release_authorized' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'requesting_clinician_id');
    }

    public function componentType(): BelongsTo
    {
        return $this->belongsTo(BloodComponentType::class, 'blood_component_type_id');
    }

    public function specimens(): HasMany
    {
        return $this->hasMany(BloodRequestSpecimen::class);
    }

    public function compatibilityTests(): HasMany
    {
        return $this->hasMany(BloodCompatibilityTest::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BloodComponentReservation::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(BloodComponentIssue::class);
    }

    public function hasUnresolvedDiscrepancy(): bool
    {
        return $this->identity_discrepancy_unresolved || $this->specimen_label_discrepancy_unresolved || $this->blood_group_discrepancy_unresolved;
    }

    public function outstandingQuantity(): int
    {
        return max(0, $this->quantity_requested - $this->quantity_issued);
    }
}
