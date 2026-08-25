<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodRequestSpecimen extends Model
{
    protected $fillable = ['hospital_id', 'blood_request_id', 'label', 'collected_at', 'collected_by', 'collection_location', 'patient_confirmed_name', 'patient_confirmed_identifier', 'label_status', 'label_discrepancy_notes', 'status', 'custody_chain', 'notes'];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime', 'custody_chain' => 'array'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BloodRequest::class, 'blood_request_id');
    }
}
