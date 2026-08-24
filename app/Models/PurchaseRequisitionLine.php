<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionLine extends Model
{
    protected $fillable = ['hospital_id', 'purchase_requisition_id', 'inventory_item_id', 'inventory_unit_id', 'quantity', 'estimated_unit_cost_minor', 'discount_minor', 'tax_minor', 'line_total_minor', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}
