<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentWorkflowService
{
    public function __construct(private readonly NumberSequenceService $numbers, private readonly AuditService $audit) {}

    public function openShift(array $data, User $actor): CashierShift
    {
        $open = CashierShift::where('hospital_id', $data['hospital_id'])->where('cashier_id', $actor->id)->where('status', 'open')->exists();
        abort_if($open, 422, 'Cashier already has an open shift.');

        $shift = CashierShift::create([
            'hospital_id' => $data['hospital_id'],
            'facility_id' => $data['facility_id'],
            'cashier_id' => $actor->id,
            'currency' => $data['currency'],
            'opening_float_minor' => (int) $data['opening_float_minor'],
            'expected_cash_minor' => (int) $data['opening_float_minor'],
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->event($shift, 'shift.opened', null, $shift->toArray(), $actor);

        return $shift;
    }

    public function closeShift(CashierShift $shift, int $countedCashMinor, User $actor): CashierShift
    {
        abort_unless($shift->cashier_id === $actor->id || $actor->can('cashier-shifts.review'), 403);
        abort_unless($shift->status === 'open', 422, 'Only open shifts can be closed.');

        $before = $shift->toArray();
        $shift->forceFill([
            'cash_collections_minor' => $this->cashCollections($shift),
            'expected_cash_minor' => $shift->opening_float_minor + $this->cashCollections($shift),
            'counted_cash_minor' => $countedCashMinor,
            'variance_minor' => $countedCashMinor - ($shift->opening_float_minor + $this->cashCollections($shift)),
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();
        $this->event($shift, 'shift.closed', $before, $shift->fresh()->toArray(), $actor);

        return $shift->refresh();
    }

    public function reviewShift(CashierShift $shift, User $actor, ?string $notes = null): CashierShift
    {
        abort_unless($shift->status === 'closed', 422, 'Only closed shifts can be reviewed.');
        abort_if($shift->cashier_id === $actor->id, 403, 'Cashiers cannot review their own shift.');
        $before = $shift->toArray();
        $shift->forceFill(['status' => 'reviewed', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes])->save();
        $this->event($shift, 'shift.reviewed', $before, $shift->fresh()->toArray(), $actor, $notes);

        return $shift->refresh();
    }

    public function postPayment(array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($data, $actor): Payment {
            if (filled($data['idempotency_key'] ?? null)) {
                $existing = Payment::where('hospital_id', $data['hospital_id'])->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $method = PaymentMethod::where('hospital_id', $data['hospital_id'])->findOrFail($data['payment_method_id']);
            $shift = $this->resolveShift($method, $data, $actor);
            $sequence = NumberSequence::where('hospital_id', $data['hospital_id'])->whereNull('facility_id')->where('key', 'receipt_number')->where('status', 'active')->firstOrFail();
            $amount = (int) $data['amount_minor'];

            $payment = Payment::create([
                'hospital_id' => $data['hospital_id'],
                'facility_id' => $data['facility_id'],
                'patient_id' => $data['patient_id'],
                'cashier_id' => $actor->id,
                'cashier_shift_id' => $shift?->id,
                'payment_method_id' => $method->id,
                'receipt_number' => $this->numbers->allocate($sequence),
                'currency' => $data['currency'],
                'amount_minor' => $amount,
                'allocated_minor' => 0,
                'unallocated_minor' => $amount,
                'status' => 'posted',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'reference_data' => $data['reference_data'] ?? [],
                'notes' => $data['notes'] ?? null,
                'posted_at' => now(),
            ]);

            foreach ($data['allocations'] ?? [] as $allocation) {
                $this->allocate($payment, Invoice::findOrFail($allocation['invoice_id']), (int) $allocation['amount_minor'], $actor);
            }

            $this->event($payment, 'payment.posted', null, $payment->fresh()->toArray(), $actor);

            return $payment->refresh();
        });
    }

    public function allocate(Payment $payment, Invoice $invoice, int $amountMinor, User $actor): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $invoice, $amountMinor, $actor): PaymentAllocation {
            abort_unless($payment->status === 'posted', 422, 'Only posted payments can be allocated.');
            abort_unless($invoice->status === 'issued', 422, 'Payments can only be allocated to issued invoices.');
            abort_unless($payment->hospital_id === $invoice->hospital_id && $payment->patient_id === $invoice->patient_id, 403);
            abort_unless($payment->currency === $invoice->currency, 422, 'Payment and invoice currency must match.');

            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->recalculateInvoice($invoice);
            abort_if($amountMinor <= 0 || $amountMinor > $payment->unallocated_minor || $amountMinor > $invoice->balance_minor, 422, 'Invalid payment allocation amount.');

            $allocation = PaymentAllocation::create([
                'hospital_id' => $payment->hospital_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount_minor' => $amountMinor,
                'status' => 'posted',
                'allocated_by' => $actor->id,
                'allocated_at' => now(),
            ]);

            $payment->forceFill([
                'allocated_minor' => $payment->allocated_minor + $amountMinor,
                'unallocated_minor' => $payment->unallocated_minor - $amountMinor,
            ])->save();
            $this->recalculateInvoice($invoice);
            $this->event($allocation, 'payment.allocated', null, $allocation->toArray(), $actor);

            return $allocation;
        });
    }

    public function reversePayment(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($payment->status === 'posted', 422, 'Only posted payments can be reversed.');
            $before = $payment->toArray();

            foreach ($payment->allocations()->where('status', 'posted')->get() as $allocation) {
                $allocation->forceFill(['status' => 'reversed', 'reversed_at' => now()])->save();
                $this->recalculateInvoice($allocation->invoice);
            }

            $payment->forceFill(['status' => 'reversed', 'reversed_at' => now(), 'reversed_by' => $actor->id, 'reversal_reason' => $reason, 'allocated_minor' => 0, 'unallocated_minor' => 0])->save();
            $this->event($payment, 'payment.reversed', $before, $payment->fresh()->toArray(), $actor, $reason);

            return $payment->refresh();
        });
    }

    public function requestRefund(Payment $payment, int $amountMinor, User $actor, string $reason): RefundRequest
    {
        abort_unless($payment->status === 'posted', 422, 'Only posted payments can be refunded.');
        $available = $payment->amount_minor - $payment->refunded_minor;
        abort_if($amountMinor <= 0 || $amountMinor > $available, 422, 'Refund exceeds available received amount.');

        $refund = RefundRequest::create([
            'hospital_id' => $payment->hospital_id,
            'facility_id' => $payment->facility_id,
            'payment_id' => $payment->id,
            'patient_id' => $payment->patient_id,
            'currency' => $payment->currency,
            'amount_minor' => $amountMinor,
            'status' => 'requested',
            'reason' => $reason,
            'requested_by' => $actor->id,
            'requested_at' => now(),
        ]);
        $this->event($refund, 'refund.requested', null, $refund->toArray(), $actor, $reason);

        return $refund;
    }

    public function decideRefund(RefundRequest $refund, string $decision, User $actor, ?string $notes = null): RefundRequest
    {
        abort_unless($refund->status === 'requested', 422, 'Refund has already been decided.');
        abort_if($refund->requested_by === $actor->id, 403, 'Cashiers cannot approve their own refund request.');
        $before = $refund->toArray();
        $refund->forceFill([
            'status' => $decision === 'approve' ? 'approved' : 'rejected',
            'approved_by' => $decision === 'approve' ? $actor->id : null,
            'approved_at' => $decision === 'approve' ? now() : null,
            'decision_notes' => $notes,
        ])->save();
        $this->event($refund, "refund.{$refund->status}", $before, $refund->fresh()->toArray(), $actor, $notes);

        return $refund->refresh();
    }

    public function processRefund(RefundRequest $refund, User $actor): RefundRequest
    {
        return DB::transaction(function () use ($refund, $actor): RefundRequest {
            $refund = RefundRequest::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless($refund->status === 'approved', 422, 'Only approved refunds can be processed.');
            $payment = Payment::whereKey($refund->payment_id)->lockForUpdate()->firstOrFail();
            abort_if($refund->amount_minor > ($payment->amount_minor - $payment->refunded_minor), 422, 'Refund exceeds available received amount.');
            $before = $refund->toArray();
            $payment->forceFill(['refunded_minor' => $payment->refunded_minor + $refund->amount_minor])->save();
            $refund->forceFill(['status' => 'processed', 'processed_by' => $actor->id, 'processed_at' => now()])->save();
            $this->event($refund, 'refund.processed', $before, $refund->fresh()->toArray(), $actor);

            return $refund->refresh();
        });
    }

    public function recalculateInvoice(Invoice $invoice): Invoice
    {
        $paid = (int) $invoice->allocations()->where('status', 'posted')->sum('amount_minor');
        $balance = max(0, $invoice->total_minor - $paid);
        $invoice->forceFill([
            'paid_minor' => $paid,
            'balance_minor' => $balance,
            'payment_status' => $paid === 0 ? 'unpaid' : ($balance === 0 ? 'paid' : 'part_paid'),
        ])->save();

        return $invoice->refresh();
    }

    private function resolveShift(PaymentMethod $method, array $data, User $actor): ?CashierShift
    {
        if (! $method->requires_open_shift) {
            return null;
        }
        $shift = CashierShift::where('hospital_id', $data['hospital_id'])->where('facility_id', $data['facility_id'])->where('cashier_id', $actor->id)->where('status', 'open')->lockForUpdate()->first();
        abort_unless($shift, 422, 'An open cashier shift is required for this payment method.');
        abort_unless($shift->currency === $data['currency'], 422, 'Shift currency must match payment currency.');

        return $shift;
    }

    private function cashCollections(CashierShift $shift): int
    {
        return (int) $shift->payments()->where('status', 'posted')->whereHas('method', fn ($query) => $query->where('type', 'cash'))->sum('amount_minor');
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        PaymentEvent::create([
            'hospital_id' => $subject->hospital_id,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'actor_id' => $actor->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
