<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadiologyReport extends Model
{
    protected $fillable = ['hospital_id', 'radiology_request_id', 'status', 'findings', 'impression', 'recommendations', 'reporting_radiologist_id', 'has_critical_finding', 'critical_finding_notes', 'entered_by', 'entered_at', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'released_at', 'released_by'];

    protected function casts(): array
    {
        return ['has_critical_finding' => 'boolean', 'entered_at' => 'datetime', 'verified_at' => 'datetime', 'approved_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(RadiologyRequest::class, 'radiology_request_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(RadiologyCriticalCommunication::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(RadiologyReportAmendment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RadiologyAttachment::class);
    }
}
