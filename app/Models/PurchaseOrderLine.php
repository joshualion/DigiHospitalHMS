<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    protected $fillable = ['hospital_id', 'purchase_order_id', 'purchase_requisition_line_id', 'inventory_item_id', 'inventory_unit_id', 'item_snapshot', 'quantity', 'received_quantity', 'accepted_quantity', 'rejected_quantity', 'unit_cost_minor', 'discount_minor', 'tax_minor', 'line_total_minor', 'notes'];

    protected function casts(): array
    {
        return ['item_snapshot' => 'array', 'quantity' => 'decimal:4', 'received_quantity' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'rejected_quantity' => 'decimal:4'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function outstandingQuantity(): string
    {
        return bcsub((string) $this->quantity, (string) $this->received_quantity, 4);
    }
}
