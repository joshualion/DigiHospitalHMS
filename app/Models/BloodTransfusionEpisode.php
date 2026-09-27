<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BloodTransfusionEpisode extends Model
{
    protected $fillable = [
        'hospital_id', 'blood_component_issue_id', 'blood_request_id', 'patient_id',
        'admission_id', 'clinical_encounter_id', 'started_by', 'completed_by', 'stopped_by',
        'started_at', 'completed_at', 'stopped_at', 'status', 'destination',
        'patient_identifier_checked', 'component_identifier_checked', 'identity_check_status',
        'stop_reason', 'notes',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'stopped_at' => 'datetime'];
    }

    public function issue(): BelongsTo { return $this->belongsTo(BloodComponentIssue::class, 'blood_component_issue_id'); }
    public function request(): BelongsTo { return $this->belongsTo(BloodRequest::class, 'blood_request_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function observations(): HasMany { return $this->hasMany(BloodTransfusionObservation::class); }
    public function reactions(): HasMany { return $this->hasMany(BloodTransfusionReaction::class); }
}
