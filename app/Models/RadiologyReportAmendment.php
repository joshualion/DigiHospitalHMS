<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyReportAmendment extends Model
{
    protected $fillable = ['hospital_id', 'radiology_report_id', 'authored_by', 'reason', 'content', 'authored_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(RadiologyReport::class, 'radiology_report_id');
    }
}
