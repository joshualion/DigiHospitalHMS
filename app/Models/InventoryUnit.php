<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryUnit extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'base_factor', 'base_unit_id', 'requires_pharmacist_validation', 'is_active'];

    protected function casts(): array
    {
        return ['base_factor' => 'decimal:6', 'requires_pharmacist_validation' => 'boolean', 'is_active' => 'boolean'];
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }
}
