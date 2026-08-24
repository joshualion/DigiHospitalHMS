<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    protected $fillable = ['hospital_id', 'goods_receipt_id', 'purchase_order_line_id', 'inventory_item_id', 'inventory_unit_id', 'inventory_batch_id', 'stock_movement_id', 'batch_number', 'manufacture_date', 'expiry_date', 'received_quantity', 'accepted_quantity', 'rejected_quantity', 'unit_cost_minor', 'batch_state', 'rejection_reason'];

    protected function casts(): array
    {
        return ['manufacture_date' => 'date', 'expiry_date' => 'date', 'received_quantity' => 'decimal:4', 'accepted_quantity' => 'decimal:4', 'rejected_quantity' => 'decimal:4'];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
