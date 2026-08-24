<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'status', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'lead_time_days', 'notes'];

    protected function casts(): array
    {
        return ['lead_time_days' => 'integer'];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class)->withPivot(['supplier_item_code', 'last_unit_cost_minor', 'currency', 'is_preferred'])->withTimestamps();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
