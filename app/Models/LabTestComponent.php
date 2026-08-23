<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTestComponent extends Model
{
    protected $fillable = ['hospital_id', 'lab_test_id', 'lab_unit_id', 'code', 'name', 'result_type', 'sort_order', 'is_required', 'is_active'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(LabUnit::class, 'lab_unit_id');
    }

    public function referenceRanges(): HasMany
    {
        return $this->hasMany(LabReferenceRange::class);
    }
}
