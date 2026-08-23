<?php

namespace App\Http\Controllers\Admin;

use App\Models\CashierShift;
use App\Models\Facility;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RefundRequest;
use App\Services\PaymentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends FoundationController
{
    public function workbench(): Response
    {
        $this->authorize('viewAny', Payment::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Payments/Workbench', $this->shared($hospital->id) + [
            'openShift' => CashierShift::with('facility:id,name')
                ->where('hospital_id', $hospital->id)
                ->where('cashier_id', request()->user()->id)
                ->where('status', 'open')
                ->latest()
                ->first(),
            'issuedInvoices' => Invoice::with('patient:id,hospital_number,first_name,middle_name,last_name')
                ->where('hospital_id', $hospital->id)
                ->where('status', 'issued')
                ->where('balance_minor', '>', 0)
                ->latest('issued_at')
                ->limit(50)
                ->get(),
            'recentPayments' => Payment::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'method:id,name,type', 'shift:id,status'])
                ->where('hospital_id', $hospital->id)
                ->latest('posted_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function accounting(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Payments/Accounting', $this->shared($hospital->id) + [
            'shifts' => CashierShift::with(['cashier:id,firstname,lastname,email', 'facility:id,name', 'reviewer:id,firstname,lastname,email'])
                ->where('hospital_id', $hospital->id)
                ->latest('opened_at')
                ->paginate(12)
                ->withQueryString(),
            'payments' => Payment::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'method:id,name,type', 'cashier:id,firstname,lastname,email'])
                ->where('hospital_id', $hospital->id)
                ->latest('posted_at')
                ->limit(50)
                ->get(),
            'refunds' => RefundRequest::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'payment:id,receipt_number', 'requester:id,firstname,lastname,email'])
                ->where('hospital_id', $hospital->id)
                ->latest('requested_at')
                ->limit(50)
                ->get(),
            'summaries' => $this->summaries($hospital->id),
        ]);
    }

    public function openShift(Request $request, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('open', CashierShift::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'currency' => ['required', 'string', 'size:3'],
            'opening_float_minor' => ['required', 'integer', 'min:0'],
        ]);
        $workflow->openShift($validated + ['hospital_id' => $hospital->id], $request->user());

        return back()->with('success', 'Cashier shift opened.');
    }

    public function closeShift(Request $request, CashierShift $shift, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('close', $shift);
        $validated = $request->validate([
            'counted_cash_minor' => ['required', 'integer', 'min:0'],
        ]);
        $workflow->closeShift($shift, (int) $validated['counted_cash_minor'], $request->user());

        return back()->with('success', 'Cashier shift closed.');
    }

    public function reviewShift(Request $request, CashierShift $shift, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('review', $shift);
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->reviewShift($shift, $request->user(), $validated['review_notes'] ?? null);

        return back()->with('success', 'Cashier shift reviewed.');
    }

    public function postPayment(Request $request, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('post', Payment::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate($this->paymentRules($hospital->id));
        $payment = $workflow->postPayment($validated + [
            'hospital_id' => $hospital->id,
            'idempotency_key' => $validated['idempotency_key'] ?? (string) Str::uuid(),
        ], $request->user());

        return redirect()->route('admin.payments.receipt', $payment)->with('success', 'Payment posted.');
    }

    public function allocate(Request $request, Payment $payment, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('allocate', $payment);
        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('hospital_id', $payment->hospital_id)],
            'amount_minor' => ['required', 'integer', 'min:1'],
        ]);
        $invoice = Invoice::where('hospital_id', $payment->hospital_id)->findOrFail($validated['invoice_id']);
        $workflow->allocate($payment, $invoice, (int) $validated['amount_minor'], $request->user());

        return back()->with('success', 'Payment allocated.');
    }

    public function receipt(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        return Inertia::render('Admin/Payments/Receipt', [
            'payment' => $payment->load(['patient', 'method', 'cashier', 'allocations.invoice.lines', 'refunds']),
        ]);
    }

    public function reverse(Request $request, Payment $payment, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('reverse', $payment);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $workflow->reversePayment($payment, $request->user(), $validated['reason']);

        return back()->with('success', 'Payment reversed.');
    }

    public function requestRefund(Request $request, Payment $payment, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('refund', $payment);
        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $workflow->requestRefund($payment, (int) $validated['amount_minor'], $request->user(), $validated['reason']);

        return back()->with('success', 'Refund requested.');
    }

    public function decideRefund(Request $request, RefundRequest $refund, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('approve', $refund);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->decideRefund($refund, $validated['decision'], $request->user(), $validated['decision_notes'] ?? null);

        return back()->with('success', 'Refund decision recorded.');
    }

    public function processRefund(Request $request, RefundRequest $refund, PaymentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('process', $refund);
        $workflow->processRefund($refund, $request->user());

        return back()->with('success', 'Refund processed.');
    }

    private function shared(int $hospitalId): array
    {
        $settings = HospitalSetting::where('hospital_id', $hospitalId)->first();

        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'patients' => Patient::where('hospital_id', $hospitalId)->latest()->limit(50)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
            'paymentMethods' => PaymentMethod::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'currency' => $settings?->currency ?? 'NGN',
        ];
    }

    private function paymentRules(int $hospitalId): array
    {
        return [
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospitalId)],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('hospital_id', $hospitalId)->where('is_active', true)],
            'currency' => ['required', 'string', 'size:3'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'reference_data' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', Rule::exists('invoices', 'id')->where('hospital_id', $hospitalId)],
            'allocations.*.amount_minor' => ['required_with:allocations', 'integer', 'min:1'],
        ];
    }

    private function summaries(int $hospitalId): array
    {
        return [
            'byMethod' => Payment::query()
                ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
                ->where('payments.hospital_id', $hospitalId)
                ->where('payments.status', 'posted')
                ->select('payment_methods.name', 'payment_methods.type', DB::raw('COUNT(*) as count'), DB::raw('SUM(payments.amount_minor) as amount_minor'))
                ->groupBy('payment_methods.name', 'payment_methods.type')
                ->orderBy('payment_methods.name')
                ->get(),
            'byFacility' => Payment::query()
                ->join('facilities', 'payments.facility_id', '=', 'facilities.id')
                ->where('payments.hospital_id', $hospitalId)
                ->where('payments.status', 'posted')
                ->select('facilities.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(payments.amount_minor) as amount_minor'))
                ->groupBy('facilities.name')
                ->orderBy('facilities.name')
                ->get(),
        ];
    }
}
