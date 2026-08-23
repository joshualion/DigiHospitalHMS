<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyRequestStudy extends Model
{
    protected $fillable = ['hospital_id', 'radiology_request_id', 'radiology_study_id', 'invoice_line_id', 'study_code', 'study_name'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(RadiologyRequest::class, 'radiology_request_id');
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(RadiologyStudy::class, 'radiology_study_id');
    }
}
