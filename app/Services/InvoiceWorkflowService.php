<?php

namespace App\Services;

use App\Models\BillableService;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceLine;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceWorkflowService
{
    public function __construct(
        private readonly ServicePricingService $pricing,
        private readonly NumberSequenceService $numbers,
        private readonly AuditService $audit,
    ) {}

    public function createDraft(array $data, Patient $patient, User $actor): Invoice
    {
        return DB::transaction(function () use ($data, $patient, $actor): Invoice {
            $invoice = Invoice::create([
                'hospital_id' => $patient->hospital_id,
                'facility_id' => $data['facility_id'] ?? null,
                'patient_id' => $patient->id,
                'visit_id' => $data['visit_id'] ?? null,
                'clinical_encounter_id' => $data['clinical_encounter_id'] ?? null,
                'replaces_invoice_id' => $data['replaces_invoice_id'] ?? null,
                'status' => 'draft',
                'currency' => $data['currency'],
                'created_by' => $actor->id,
            ]);

            $this->recalculate($invoice);
            $this->event($invoice, 'created', null, 'draft', null, $invoice->toArray(), $actor);
            $this->audit->record('invoices.created', $invoice, null, $invoice->toArray(), actor: $actor);

            return $invoice;
        });
    }

    public function addServiceLine(Invoice $invoice, BillableService $service, array $data, User $actor): InvoiceLine
    {
        abort_unless($invoice->isMutableDraft(), 422, 'Only draft invoices can be changed.');
        abort_unless($invoice->hospital_id === $service->hospital_id, 403);

        return DB::transaction(function () use ($invoice, $service, $data, $actor): InvoiceLine {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->isMutableDraft(), 422, 'Only draft invoices can be changed.');

            $quantity = max(1, (int) ($data['quantity'] ?? 1));
            $price = $this->pricing->priceFor($service, $invoice->facility_id, $data['priced_at'] ?? null);
            abort_unless($price->currency === $invoice->currency, 422, 'Service price currency must match invoice currency.');

            $subtotal = $price->amount_minor * $quantity;
            $discount = $service->is_discount_eligible ? min((int) ($data['discount_minor'] ?? 0), $subtotal) : 0;
            $taxable = max(0, $subtotal - $discount);
            $tax = $service->is_tax_exempt ? 0 : intdiv($taxable * (int) $service->tax_rate_basis_points, 10000);
            $total = $taxable + $tax;

            $line = InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'hospital_id' => $invoice->hospital_id,
                'billable_service_id' => $service->id,
                'line_type' => 'service',
                'service_code' => $service->code,
                'service_name' => $service->name,
                'service_description' => $service->description,
                'quantity' => $quantity,
                'unit_price_minor' => $price->amount_minor,
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'tax_minor' => $tax,
                'total_minor' => $total,
                'tax_rate_basis_points' => $service->is_tax_exempt ? 0 : $service->tax_rate_basis_points,
                'tax_exempt' => $service->is_tax_exempt,
                'discount_eligible' => $service->is_discount_eligible,
                'created_by' => $actor->id,
            ]);

            $this->recalculate($invoice);
            $this->event($invoice, 'line_added', 'draft', 'draft', null, $line->toArray(), $actor);
            $this->audit->record('invoices.line_added', $line, null, $line->toArray(), actor: $actor);

            return $line;
        });
    }

    public function addManualLine(Invoice $invoice, array $data, User $actor): InvoiceLine
    {
        abort_unless($invoice->isMutableDraft(), 422, 'Only draft invoices can be changed.');

        return DB::transaction(function () use ($invoice, $data, $actor): InvoiceLine {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->isMutableDraft(), 422, 'Only draft invoices can be changed.');

            $quantity = max(1, (int) ($data['quantity'] ?? 1));
            $unit = (int) $data['unit_price_minor'];
            $subtotal = $unit * $quantity;
            $discount = min((int) ($data['discount_minor'] ?? 0), $subtotal);
            $taxable = max(0, $subtotal - $discount);
            $rate = (int) ($data['tax_rate_basis_points'] ?? 0);
            $taxExempt = (bool) ($data['tax_exempt'] ?? false);
            $tax = $taxExempt ? 0 : intdiv($taxable * $rate, 10000);

            $line = InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'hospital_id' => $invoice->hospital_id,
                'line_type' => 'manual',
                'service_name' => $data['service_name'],
                'service_description' => $data['service_description'] ?? null,
                'quantity' => $quantity,
                'unit_price_minor' => $unit,
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'tax_minor' => $tax,
                'total_minor' => $taxable + $tax,
                'tax_rate_basis_points' => $taxExempt ? 0 : $rate,
                'tax_exempt' => $taxExempt,
                'discount_eligible' => true,
                'manual_reason' => $data['manual_reason'],
                'created_by' => $actor->id,
            ]);

            $this->recalculate($invoice);
            $this->event($invoice, 'manual_line_added', 'draft', 'draft', null, $line->toArray(), $actor, $data['manual_reason']);
            $this->audit->record('invoices.manual_line_added', $line, null, $line->toArray(), actor: $actor, reason: $data['manual_reason']);

            return $line;
        });
    }

    public function issue(Invoice $invoice, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor): Invoice {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->status === 'draft', 422, 'Only draft invoices can be issued.');
            abort_if($invoice->lines()->count() === 0, 422, 'Cannot issue an empty invoice.');

            $sequence = NumberSequence::where('hospital_id', $invoice->hospital_id)->whereNull('facility_id')->where('key', 'invoice_number')->where('status', 'active')->firstOrFail();
            $before = $invoice->toArray();
            $invoice->forceFill([
                'invoice_number' => $this->numbers->allocate($sequence),
                'status' => 'issued',
                'paid_minor' => 0,
                'balance_minor' => $invoice->total_minor,
                'payment_status' => $invoice->total_minor > 0 ? 'unpaid' : 'paid',
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ])->save();

            $this->event($invoice, 'issued', 'draft', 'issued', $before, $invoice->fresh()->toArray(), $actor);
            $this->audit->record('invoices.issued', $invoice, $before, $invoice->fresh()->toArray(), actor: $actor);

            return $invoice->refresh();
        });
    }

    public function transition(Invoice $invoice, string $action, User $actor, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $action, $actor, $reason): Invoice {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $before = $invoice->toArray();
            $updates = match ($action) {
                'cancel' => $this->assertStatus($invoice, ['draft'], ['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'status_reason' => $reason]),
                'void' => $this->assertStatus($invoice, ['issued'], ['status' => 'voided', 'voided_by' => $actor->id, 'voided_at' => now(), 'status_reason' => $reason]),
                default => abort(422, 'Unsupported invoice transition.'),
            };
            $from = $invoice->status;
            $invoice->forceFill($updates)->save();
            $this->event($invoice, $action, $from, $invoice->status, $before, $invoice->fresh()->toArray(), $actor, $reason);
            $this->audit->record("invoices.{$action}", $invoice, $before, $invoice->fresh()->toArray(), actor: $actor, reason: $reason);

            return $invoice->refresh();
        });
    }

    public function replacementDraft(Invoice $voided, User $actor): Invoice
    {
        abort_unless($voided->status === 'voided', 422, 'Only voided invoices can be replaced.');

        return DB::transaction(function () use ($voided, $actor): Invoice {
            $draft = $this->createDraft([
                'facility_id' => $voided->facility_id,
                'visit_id' => $voided->visit_id,
                'clinical_encounter_id' => $voided->clinical_encounter_id,
                'replaces_invoice_id' => $voided->id,
                'currency' => $voided->currency,
            ], $voided->patient, $actor);
            $voided->forceFill(['replaced_by_invoice_id' => $draft->id])->save();

            return $draft;
        });
    }

    public function recalculate(Invoice $invoice): void
    {
        $totals = $invoice->lines()
            ->selectRaw('COALESCE(SUM(subtotal_minor),0) as subtotal, COALESCE(SUM(discount_minor),0) as discount, COALESCE(SUM(tax_minor),0) as tax, COALESCE(SUM(total_minor),0) as total')
            ->first();

        $invoice->forceFill([
            'subtotal_minor' => (int) $totals->subtotal,
            'discount_minor' => (int) $totals->discount,
            'tax_minor' => (int) $totals->tax,
            'total_minor' => (int) $totals->total,
        ])->save();
    }

    private function assertStatus(Invoice $invoice, array $allowed, array $updates): array
    {
        abort_unless(in_array($invoice->status, $allowed, true), 422, 'Invalid invoice transition.');

        return $updates;
    }

    private function event(Invoice $invoice, string $action, ?string $from, ?string $to, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        InvoiceEvent::create([
            'invoice_id' => $invoice->id,
            'hospital_id' => $invoice->hospital_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
