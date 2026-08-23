<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = ['invoice_id', 'hospital_id', 'billable_service_id', 'line_type', 'service_code', 'service_name', 'service_description', 'quantity', 'unit_price_minor', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'tax_rate_basis_points', 'tax_exempt', 'discount_eligible', 'manual_reason', 'created_by'];

    protected function casts(): array
    {
        return ['tax_exempt' => 'boolean', 'discount_eligible' => 'boolean'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
