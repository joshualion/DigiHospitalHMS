<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = ['hospital_id', 'base_unit_id', 'sku', 'barcode', 'type', 'generic_name', 'brand_name', 'name', 'dosage_form', 'strength', 'route', 'description', 'reorder_level', 'requires_pharmacist_validation', 'is_active'];

    protected function casts(): array
    {
        return ['reorder_level' => 'decimal:4', 'requires_pharmacist_validation' => 'boolean', 'is_active' => 'boolean'];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'base_unit_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }
}
