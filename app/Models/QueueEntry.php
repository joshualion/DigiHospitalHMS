<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueEntry extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'department_id', 'visit_id', 'patient_id', 'clinician_id', 'queue_date', 'queue_number', 'priority', 'status', 'called_at', 'removed_at', 'created_by'];

    protected function casts(): array
    {
        return ['queue_date' => 'date', 'called_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'clinician_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QueueEvent::class);
    }
}
