<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionDispense extends Model
{
    protected $fillable = ['hospital_id', 'prescription_id', 'prescription_item_id', 'inventory_location_id', 'inventory_batch_id', 'stock_movement_id', 'source_dispense_id', 'quantity', 'action', 'instructions', 'reason', 'performed_by', 'performed_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'performed_at' => 'datetime'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function sourceDispense(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_dispense_id');
    }
}
