<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionItem extends Model
{
    protected $fillable = ['hospital_id', 'prescription_id', 'inventory_item_id', 'inventory_unit_id', 'invoice_line_id', 'medicine_name', 'dose', 'route', 'frequency', 'duration', 'quantity', 'dispensed_quantity', 'instructions', 'indication', 'is_prn', 'medication_order_type', 'scheduled_times', 'start_at', 'end_at', 'prn_instructions', 'status'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'dispensed_quantity' => 'decimal:4', 'is_prn' => 'boolean', 'scheduled_times' => 'array', 'start_at' => 'datetime', 'end_at' => 'datetime'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(PrescriptionDispense::class);
    }

    public function emarSchedules(): HasMany
    {
        return $this->hasMany(EmarSchedule::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(EmarAdministration::class);
    }

    public function outstandingQuantity(): string
    {
        return bcsub((string) $this->quantity, (string) $this->dispensed_quantity, 4);
    }
}
