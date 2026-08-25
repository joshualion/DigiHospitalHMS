<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodCompatibilityTest extends Model
{
    protected $fillable = ['hospital_id', 'blood_request_id', 'blood_request_specimen_id', 'blood_component_id', 'test_type', 'result', 'interpretation', 'status', 'notes', 'entered_by', 'entered_at', 'authorized_by', 'authorized_at'];

    protected function casts(): array
    {
        return ['entered_at' => 'datetime', 'authorized_at' => 'datetime'];
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
