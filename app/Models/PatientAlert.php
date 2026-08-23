<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAlert extends Model
{
    protected $fillable = ['patient_id', 'hospital_id', 'title', 'category', 'severity', 'status', 'notes', 'recorded_by', 'recorded_at', 'updated_by'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
