<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientActivityEvent extends Model
{
    protected $fillable = ['patient_id', 'hospital_id', 'actor_id', 'action', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
