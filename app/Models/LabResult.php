<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    protected $fillable = ['hospital_id', 'lab_request_id', 'lab_request_test_id', 'lab_test_component_id', 'lab_unit_id', 'component_code', 'component_name', 'result_type', 'numeric_value', 'text_value', 'qualitative_value', 'comment', 'reference_range_snapshot', 'flag', 'is_critical', 'status', 'entered_by', 'entered_at', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'critical_acknowledged_by', 'critical_acknowledged_at', 'critical_escalation_notes'];

    protected function casts(): array
    {
        return ['reference_range_snapshot' => 'array', 'is_critical' => 'boolean', 'entered_at' => 'datetime', 'verified_at' => 'datetime', 'approved_at' => 'datetime', 'critical_acknowledged_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    public function requestTest(): BelongsTo
    {
        return $this->belongsTo(LabRequestTest::class, 'lab_request_test_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(LabTestComponent::class, 'lab_test_component_id');
    }
}
