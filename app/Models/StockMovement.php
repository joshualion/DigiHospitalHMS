<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['hospital_id', 'inventory_item_id', 'inventory_batch_id', 'from_location_id', 'to_location_id', 'inventory_unit_id', 'movement_type', 'quantity', 'base_quantity', 'unit_cost_minor', 'currency', 'reference_type', 'reference_id', 'reverses_movement_id', 'reason', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'base_quantity' => 'decimal:4', 'posted_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }
}
