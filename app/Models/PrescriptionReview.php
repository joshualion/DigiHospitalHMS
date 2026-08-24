<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionReview extends Model
{
    protected $fillable = ['hospital_id', 'prescription_id', 'prescription_item_id', 'action', 'reason', 'substituted_inventory_item_id', 'substitution_note', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
