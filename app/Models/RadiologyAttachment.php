<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyAttachment extends Model
{
    protected $fillable = ['hospital_id', 'radiology_request_id', 'radiology_report_id', 'uploaded_by', 'disk', 'path', 'original_name', 'stored_name', 'mime_type', 'extension', 'size_bytes', 'scan_status', 'status', 'cleared_at', 'retired_by', 'retired_at', 'retirement_reason'];

    protected function casts(): array
    {
        return ['cleared_at' => 'datetime', 'retired_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RadiologyRequest::class, 'radiology_request_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(RadiologyReport::class, 'radiology_report_id');
    }
}
