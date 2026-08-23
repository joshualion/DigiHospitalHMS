<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RadiologyStudy extends Model
{
    protected $fillable = ['hospital_id', 'radiology_modality_id', 'billable_service_id', 'code', 'name', 'description', 'preparation_acknowledgements', 'safety_screening_acknowledgements', 'requires_professional_validation', 'is_active'];

    protected function casts(): array
    {
        return ['preparation_acknowledgements' => 'array', 'safety_screening_acknowledgements' => 'array', 'requires_professional_validation' => 'boolean', 'is_active' => 'boolean'];
    }

    public function modality(): BelongsTo
    {
        return $this->belongsTo(RadiologyModality::class, 'radiology_modality_id');
    }

    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class);
    }
}
