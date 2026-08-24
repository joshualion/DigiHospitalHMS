<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalEncounter extends Model
{
    protected $fillable = [
        'hospital_id',
        'facility_id',
        'department_id',
        'patient_id',
        'visit_id',
        'appointment_id',
        'queue_entry_id',
        'responsible_clinician_id',
        'source',
        'status',
        'presenting_complaint',
        'history_presenting_complaint',
        'medical_history',
        'surgical_history',
        'medication_history',
        'family_history',
        'social_history',
        'examination_findings',
        'treatment_plan',
        'follow_up_instructions',
        'follow_up_date',
        'referral_recommendation',
        'started_by',
        'started_at',
        'paused_by',
        'paused_at',
        'resumed_by',
        'resumed_at',
        'signed_by',
        'signed_at',
        'cancelled_by',
        'cancelled_at',
        'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_date' => 'date',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'resumed_at' => 'datetime',
            'signed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(QueueEntry::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'responsible_clinician_id');
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(EncounterVital::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(EncounterDiagnosis::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClinicalEncounterEvent::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(EncounterAmendment::class);
    }

    public function labRequests(): HasMany
    {
        return $this->hasMany(LabRequest::class);
    }

    public function radiologyRequests(): HasMany
    {
        return $this->hasMany(RadiologyRequest::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }
}
