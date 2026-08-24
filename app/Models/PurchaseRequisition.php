<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequisition extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'inventory_location_id', 'status', 'currency', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'reason', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'decision_reason', 'converted_purchase_order_id'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'converted_purchase_order_id');
    }
}
