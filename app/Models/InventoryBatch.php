<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    protected $fillable = ['hospital_id', 'inventory_item_id', 'batch_number', 'manufacture_date', 'expiry_date', 'supplier_reference', 'currency', 'unit_cost_minor', 'state'];

    protected function casts(): array
    {
        return ['manufacture_date' => 'date', 'expiry_date' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function isDispensableCandidate(): bool
    {
        return $this->state === 'available' && (! $this->expiry_date || $this->expiry_date->isFuture());
    }
}
