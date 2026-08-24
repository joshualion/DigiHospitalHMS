<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturn extends Model
{
    protected $fillable = ['hospital_id', 'goods_receipt_line_id', 'inventory_location_id', 'inventory_batch_id', 'stock_movement_id', 'quantity', 'action', 'reason', 'performed_by', 'performed_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'performed_at' => 'datetime'];
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
