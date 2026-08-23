<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'patient_id', 'visit_id', 'clinical_encounter_id', 'replaces_invoice_id', 'replaced_by_invoice_id', 'invoice_number', 'status', 'currency', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor', 'paid_minor', 'balance_minor', 'payment_status', 'created_by', 'issued_by', 'issued_at', 'cancelled_by', 'cancelled_at', 'voided_by', 'voided_at', 'status_reason'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'cancelled_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class);
    }

    public function isMutableDraft(): bool
    {
        return $this->status === 'draft';
    }
}
