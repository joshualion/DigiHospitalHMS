<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'supplier_id', 'purchase_requisition_id', 'purchase_order_number', 'status', 'currency', 'supplier_snapshot', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'created_by', 'approved_by', 'approved_at', 'closed_at'];

    protected $appends = ['outstanding_quantity'];

    protected function casts(): array
    {
        return ['supplier_snapshot' => 'array', 'approved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function getOutstandingQuantityAttribute(): string
    {
        return $this->lines->reduce(fn (string $carry, PurchaseOrderLine $line): string => bcadd($carry, $line->outstandingQuantity(), 4), '0.0000');
    }
}
