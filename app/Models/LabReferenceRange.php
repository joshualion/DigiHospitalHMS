<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabReferenceRange extends Model
{
    protected $fillable = ['hospital_id', 'lab_test_component_id', 'label', 'low_value', 'high_value', 'critical_low_value', 'critical_high_value', 'qualitative_normal', 'display_text', 'criteria', 'requires_professional_validation', 'is_active'];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'requires_professional_validation' => 'boolean', 'is_active' => 'boolean'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(LabTestComponent::class, 'lab_test_component_id');
    }
}
