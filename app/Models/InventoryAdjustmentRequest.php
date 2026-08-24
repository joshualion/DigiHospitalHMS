<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentRequest extends Model
{
    protected $fillable = ['hospital_id', 'inventory_location_id', 'inventory_item_id', 'inventory_batch_id', 'quantity_delta', 'status', 'reason', 'requested_by', 'requested_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason'];

    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:4', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }
}
