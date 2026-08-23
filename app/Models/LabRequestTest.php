<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabRequestTest extends Model
{
    protected $fillable = ['hospital_id', 'lab_request_id', 'lab_test_id', 'invoice_line_id', 'test_code', 'test_name', 'status', 'component_snapshot'];

    protected function casts(): array
    {
        return ['component_snapshot' => 'array'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class);
    }
}
