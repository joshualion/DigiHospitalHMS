<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodComponentIssue extends Model
{
    protected $fillable = ['hospital_id', 'blood_request_id', 'blood_component_reservation_id', 'blood_component_id', 'issue_number', 'patient_id', 'issued_by', 'received_by_name', 'receiver_role', 'issued_at', 'destination', 'status', 'returned_at', 'returned_by', 'return_reason', 'return_assessed_by', 'return_assessed_at', 'return_assessment', 'reversed_at', 'reversed_by', 'reversal_reason'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'returned_at' => 'datetime', 'return_assessed_at' => 'datetime', 'reversed_at' => 'datetime'];
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
