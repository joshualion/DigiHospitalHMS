<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\PaymentWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase3BPaymentsReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private User $cashier;

    private User $accountant;

    private Patient $patient;

    private PaymentMethod $cash;

    private PaymentMethod $transfer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => 'receipt_number', 'label' => 'Receipt', 'prefix' => 'RCT', 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);

        $this->cashier = $this->staffUser(['payments.view', 'payments.post', 'refunds.request', 'cashier-shifts.open', 'cashier-shifts.close'], 'cashier');
        $this->accountant = $this->staffUser(['payments.view', 'payments.reverse', 'refunds.approve', 'refunds.process', 'cashier-shifts.review'], 'accountant');
        $this->patient = $this->patient();
        $this->cash = PaymentMethod::create(['hospital_id' => $this->hospital->id, 'code' => 'CASH', 'name' => 'Cash', 'type' => 'cash', 'requires_open_shift' => true, 'is_active' => true]);
        $this->transfer = PaymentMethod::create(['hospital_id' => $this->hospital->id, 'code' => 'TRANSFER', 'name' => 'Bank transfer', 'type' => 'transfer', 'requires_open_shift' => false, 'is_active' => true]);
    }

    public function test_partial_multiple_allocations_and_invoice_balances_are_server_derived(): void
    {
        $first = $this->issuedInvoice(10000);
        $second = $this->issuedInvoice(7000, 'INV-002');
        $workflow = app(PaymentWorkflowService::class);

        $payment = $workflow->postPayment($this->paymentPayload(12000, $this->transfer, [
            ['invoice_id' => $first->id, 'amount_minor' => 8000],
            ['invoice_id' => $second->id, 'amount_minor' => 4000],
        ]), $this->cashier);

        $this->assertSame(12000, $payment->refresh()->allocated_minor);
        $this->assertSame(0, $payment->unallocated_minor);
        $this->assertSame('part_paid', $first->refresh()->payment_status);
        $this->assertSame(2000, $first->balance_minor);
        $this->assertSame(3000, $second->refresh()->balance_minor);
    }

    public function test_deposit_can_be_allocated_later_without_frontend_totals(): void
    {
        $invoice = $this->issuedInvoice(5000);
        $workflow = app(PaymentWorkflowService::class);
        $payment = $workflow->postPayment($this->paymentPayload(5000, $this->transfer, []), $this->cashier);

        $this->assertSame(5000, $payment->refresh()->unallocated_minor);
        $workflow->allocate($payment->fresh(), $invoice, 5000, $this->cashier);

        $this->assertSame('paid', $invoice->refresh()->payment_status);
        $this->assertSame(0, $invoice->balance_minor);
        $this->assertSame(0, $payment->refresh()->unallocated_minor);
    }

    public function test_receipt_numbering_and_duplicate_submission_are_idempotent(): void
    {
        $workflow = app(PaymentWorkflowService::class);
        $payload = $this->paymentPayload(3000, $this->transfer, [], ['idempotency_key' => 'same-submit']);

        $first = $workflow->postPayment($payload, $this->cashier);
        $second = $workflow->postPayment($payload, $this->cashier);

        $this->assertTrue($first->is($second));
        $this->assertSame('RCT-'.now()->format('Y').'-0001', $first->receipt_number);
        $this->assertSame(1, Payment::count());
    }

    public function test_cash_shift_close_variance_and_closed_shift_protection(): void
    {
        $workflow = app(PaymentWorkflowService::class);
        $shift = $workflow->openShift(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'currency' => 'NGN', 'opening_float_minor' => 1000], $this->cashier);
        $workflow->postPayment($this->paymentPayload(2500, $this->cash), $this->cashier);
        $workflow->closeShift($shift->fresh(), 3300, $this->cashier);

        $this->assertSame('closed', $shift->refresh()->status);
        $this->assertSame(3500, $shift->expected_cash_minor);
        $this->assertSame(-200, $shift->variance_minor);

        $this->expectException(HttpException::class);
        $workflow->postPayment($this->paymentPayload(500, $this->cash), $this->cashier);
    }

    public function test_reversal_refund_limits_and_approval_separation(): void
    {
        $invoice = $this->issuedInvoice(6000);
        $workflow = app(PaymentWorkflowService::class);
        $payment = $workflow->postPayment($this->paymentPayload(6000, $this->transfer, [['invoice_id' => $invoice->id, 'amount_minor' => 6000]]), $this->cashier);
        $refund = $workflow->requestRefund($payment->fresh(), 2000, $this->cashier, 'Duplicate card charge');

        $this->expectException(HttpException::class);
        $workflow->decideRefund($refund->fresh(), 'approve', $this->cashier);
    }

    public function test_refund_processing_and_payment_reversal_preserve_history(): void
    {
        $invoice = $this->issuedInvoice(6000);
        $workflow = app(PaymentWorkflowService::class);
        $payment = $workflow->postPayment($this->paymentPayload(6000, $this->transfer, [['invoice_id' => $invoice->id, 'amount_minor' => 6000]]), $this->cashier);
        $refund = $workflow->requestRefund($payment->fresh(), 2000, $this->cashier, 'Patient refund');
        $workflow->decideRefund($refund->fresh(), 'approve', $this->accountant, 'Approved');
        $workflow->processRefund($refund->fresh(), $this->accountant);

        $this->assertSame(2000, $payment->refresh()->refunded_minor);

        $workflow->reversePayment($payment->fresh(), $this->accountant, 'Wrong patient');
        $this->assertSame('reversed', $payment->refresh()->status);
        $this->assertSame('unpaid', $invoice->refresh()->payment_status);
        $this->assertDatabaseHas('payment_events', ['action' => 'payment.reversed']);
        $this->assertDatabaseHas('audit_events', ['action' => 'payment.reversed']);
    }

    public function test_authorization_scoping_and_inertia_pages(): void
    {
        $invoice = $this->issuedInvoice(4000);

        $this->actingAs($this->cashier)->get('/admin/payments/workbench')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Payments/Workbench')->has('paymentMethods')->has('issuedInvoices'));

        $this->actingAs($this->cashier)->get('/admin/payments/accounting')->assertOk();
        $this->actingAs($this->cashier)->patch('/admin/payments/999/reverse', ['reason' => 'Denied'])->assertNotFound();

        $payment = app(PaymentWorkflowService::class)->postPayment($this->paymentPayload(4000, $this->transfer, [['invoice_id' => $invoice->id, 'amount_minor' => 4000]]), $this->cashier);
        $this->actingAs($this->cashier)->patch("/admin/payments/{$payment->id}/reverse", ['reason' => 'Denied'])->assertForbidden();

        $otherHospital = Hospital::factory()->create();
        $otherPayment = Payment::create([
            'hospital_id' => $otherHospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'cashier_id' => $this->cashier->id,
            'payment_method_id' => $this->transfer->id,
            'receipt_number' => 'OTHER',
            'currency' => 'NGN',
            'amount_minor' => 100,
            'unallocated_minor' => 100,
            'status' => 'posted',
            'posted_at' => now(),
        ]);
        $this->actingAs($this->accountant)->get("/admin/payments/{$otherPayment->id}/receipt")->assertForbidden();
    }

    public function test_cashier_shift_review_requires_different_authorized_user(): void
    {
        $workflow = app(PaymentWorkflowService::class);
        $shift = $workflow->openShift(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'currency' => 'NGN', 'opening_float_minor' => 0], $this->cashier);
        $workflow->closeShift($shift->fresh(), 0, $this->cashier);

        $this->actingAs($this->cashier)->patch("/admin/cashier-shifts/{$shift->id}/review", ['review_notes' => 'Self review'])->assertForbidden();
        $this->actingAs($this->accountant)->patch("/admin/cashier-shifts/{$shift->id}/review", ['review_notes' => 'Checked'])->assertRedirect();
        $this->assertSame('reviewed', $shift->refresh()->status);
    }

    private function paymentPayload(int $amount, PaymentMethod $method, array $allocations = [], array $overrides = []): array
    {
        return array_merge([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'payment_method_id' => $method->id,
            'currency' => 'NGN',
            'amount_minor' => $amount,
            'allocations' => $allocations,
            'reference_data' => ['reference' => 'REF-1'],
        ], $overrides);
    }

    private function issuedInvoice(int $total, string $number = 'INV-001'): Invoice
    {
        $invoice = Invoice::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => $number,
            'status' => 'issued',
            'currency' => 'NGN',
            'subtotal_minor' => $total,
            'total_minor' => $total,
            'balance_minor' => $total,
            'payment_status' => 'unpaid',
            'created_by' => $this->accountant->id,
            'issued_by' => $this->accountant->id,
            'issued_at' => now(),
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'hospital_id' => $this->hospital->id,
            'line_type' => 'manual',
            'service_name' => 'Consultation',
            'quantity' => 1,
            'unit_price_minor' => $total,
            'subtotal_minor' => $total,
            'total_minor' => $total,
            'manual_reason' => 'Test service',
            'created_by' => $this->accountant->id,
        ]);

        return $invoice;
    }

    private function patient(): Patient
    {
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->cashier->id,
            'hospital_number' => 'PAT-PAY',
            'first_name' => 'Paying',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-01',
            'sex' => 'female',
            'status' => 'active',
        ]);
        $patient->phone = '08030000000';
        $patient->save();

        return $patient;
    }

    private function staffUser(array $permissions, string $role): User
    {
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->syncRoles([$role]);
        $user->givePermissionTo($permissions);
        StaffProfile::factory()->create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'is_active' => true]);

        return $user->load('staffProfile');
    }
}
