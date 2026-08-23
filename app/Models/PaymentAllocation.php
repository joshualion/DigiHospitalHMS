<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = ['hospital_id', 'payment_id', 'invoice_id', 'amount_minor', 'status', 'allocated_by', 'allocated_at', 'reversed_at'];

    protected function casts(): array
    {
        return ['allocated_at' => 'datetime', 'reversed_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
