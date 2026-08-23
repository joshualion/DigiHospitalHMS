<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RadiologyRequest extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'ordering_clinician_id', 'ordered_by', 'invoice_id', 'request_number', 'accession_number', 'status', 'priority', 'clinical_indication', 'preparation_acknowledged', 'safety_screening_acknowledged', 'ordered_at', 'scheduled_at', 'room', 'equipment', 'assigned_staff_id', 'arrived_at', 'performed_at', 'performance_notes', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'released_at', 'released_by'];

    protected function casts(): array
    {
        return ['preparation_acknowledged' => 'array', 'safety_screening_acknowledged' => 'array', 'ordered_at' => 'datetime', 'scheduled_at' => 'datetime', 'arrived_at' => 'datetime', 'performed_at' => 'datetime', 'cancelled_at' => 'datetime', 'released_at' => 'datetime'];
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function studies(): HasMany
    {
        return $this->hasMany(RadiologyRequestStudy::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(RadiologyReport::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RadiologyAttachment::class);
    }
}
