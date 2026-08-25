<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodComponentReservation extends Model
{
    protected $fillable = ['hospital_id', 'blood_request_id', 'blood_component_id', 'status', 'reserved_at', 'expires_at', 'reserved_by', 'released_at', 'released_by', 'release_reason'];

    protected function casts(): array
    {
        return ['reserved_at' => 'datetime', 'expires_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class, 'blood_request_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(BloodComponent::class, 'blood_component_id');
    }
}
