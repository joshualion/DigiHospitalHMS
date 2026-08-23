<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabSpecimen extends Model
{
    protected $fillable = ['hospital_id', 'lab_request_id', 'lab_specimen_type_id', 'label_number', 'status', 'collected_by', 'collected_at', 'received_by', 'received_at', 'rejected_by', 'rejected_at', 'rejection_reason'];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'received_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LabSpecimenType::class, 'lab_specimen_type_id');
    }
}
