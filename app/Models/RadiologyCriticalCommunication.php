<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyCriticalCommunication extends Model
{
    protected $fillable = ['hospital_id', 'radiology_report_id', 'communicated_by', 'communicated_to', 'method', 'notes', 'communicated_at', 'acknowledged_by', 'acknowledged_at', 'escalation_notes'];

    protected function casts(): array
    {
        return ['communicated_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(RadiologyReport::class, 'radiology_report_id');
    }
}
