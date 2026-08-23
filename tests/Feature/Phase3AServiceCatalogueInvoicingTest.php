<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\PublicSiteItem;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\InvoiceWorkflowService;
use App\Services\ServicePricingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase3AServiceCatalogueInvoicingTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private Department $department;

    private User $billingUser;

    private Patient $patient;

    private BillableServiceCategory $category;

    private BillableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        $this->department = Department::factory()->create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => 'invoice_number', 'label' => 'Invoice', 'prefix' => 'INV', 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        $this->billingUser = $this->staffUser(['billing.catalogue.view', 'billing.catalogue.manage', 'invoices.view', 'invoices.create', 'invoices.issue', 'invoices.void'], 'accountant');
        $this->patient = $this->patient();
        $this->category = BillableServiceCategory::create(['hospital_id' => $this->hospital->id, 'name' => 'Consultation', 'code' => 'CONS', 'is_active' => true]);
        $public = PublicSiteItem::create(['hospital_id' => $this->hospital->id, 'type' => 'service', 'slug' => 'public-consultation', 'title' => 'Public consultation', 'status' => 'published', 'is_enabled' => true]);
        $this->service = BillableService::create([
            'hospital_id' => $this->hospital->id,
            'billable_service_category_id' => $this->category->id,
            'department_id' => $this->department->id,
            'public_site_item_id' => $public->id,
            'code' => 'GEN',
            'name' => 'General consultation',
            'description' => 'Published service snapshot text',
            'is_tax_exempt' => false,
            'tax_rate_basis_points' => 750,
            'is_discount_eligible' => true,
            'is_active' => true,
        ]);
        $this->service->facilities()->sync([$this->facility->id]);
    }

    public function test_pricing_precedence_effective_dates_and_overlap_protection(): void
    {
        $pricing = app(ServicePricingService::class);
        $pricing->createPrice($this->service, ['currency' => 'NGN', 'amount_minor' => 10000, 'effective_from' => '2026-01-01', 'reason' => 'Default'], $this->billingUser);
        $pricing->createPrice($this->service, ['facility_id' => $this->facility->id, 'currency' => 'NGN', 'amount_minor' => 12000, 'effective_from' => '2026-02-01', 'effective_to' => '2026-12-31', 'reason' => 'Facility'], $this->billingUser);

        $this->assertSame(10000, $pricing->priceFor($this->service, $this->facility->id, '2026-01-15')->amount_minor);
        $this->assertSame(12000, $pricing->priceFor($this->service, $this->facility->id, '2026-03-01')->amount_minor);

        $this->expectException(HttpException::class);
        $pricing->createPrice($this->service, ['facility_id' => $this->facility->id, 'currency' => 'NGN', 'amount_minor' => 13000, 'effective_from' => '2026-06-01', 'reason' => 'Overlap'], $this->billingUser);
    }

    public function test_invoice_calculations_are_server_side_and_snapshotted(): void
    {
        app(ServicePricingService::class)->createPrice($this->service, ['currency' => 'NGN', 'amount_minor' => 10000, 'effective_from' => '2026-01-01', 'reason' => 'Default'], $this->billingUser);
        $workflow = app(InvoiceWorkflowService::class);
        $invoice = $workflow->createDraft(['facility_id' => $this->facility->id, 'currency' => 'NGN'], $this->patient, $this->billingUser);
        $workflow->addServiceLine($invoice, $this->service, ['quantity' => 2, 'discount_minor' => 1000, 'total_minor' => 1], $this->billingUser);
        $workflow->addManualLine($invoice->fresh(), ['service_name' => 'Manual approved line', 'quantity' => 1, 'unit_price_minor' => 5000, 'discount_minor' => 0, 'tax_rate_basis_points' => 0, 'tax_exempt' => true, 'manual_reason' => 'Approved manual adjustment'], $this->billingUser);

        $invoice->refresh();
        $this->assertSame(25000, $invoice->subtotal_minor);
        $this->assertSame(1000, $invoice->discount_minor);
        $this->assertSame(1425, $invoice->tax_minor);
        $this->assertSame(25425, $invoice->total_minor);
        $this->assertDatabaseHas('invoice_lines', ['service_code' => 'GEN', 'service_name' => 'General consultation', 'service_description' => 'Published service snapshot text', 'total_minor' => 20425]);
    }

    public function test_invoice_issue_immutability_void_and_replacement(): void
    {
        app(ServicePricingService::class)->createPrice($this->service, ['currency' => 'NGN', 'amount_minor' => 10000, 'effective_from' => '2026-01-01', 'reason' => 'Default'], $this->billingUser);
        $workflow = app(InvoiceWorkflowService::class);
        $invoice = $workflow->createDraft(['facility_id' => $this->facility->id, 'currency' => 'NGN'], $this->patient, $this->billingUser);
        $workflow->addServiceLine($invoice, $this->service, ['quantity' => 1], $this->billingUser);

        $workflow->issue($invoice->fresh(), $this->billingUser);
        $this->assertSame('issued', $invoice->refresh()->status);
        $this->assertSame('INV-'.now()->format('Y').'-0001', $invoice->invoice_number);
        $this->expectException(HttpException::class);
        $workflow->addServiceLine($invoice->fresh(), $this->service, ['quantity' => 1], $this->billingUser);
    }

    public function test_void_and_replacement_draft(): void
    {
        app(ServicePricingService::class)->createPrice($this->service, ['currency' => 'NGN', 'amount_minor' => 10000, 'effective_from' => '2026-01-01', 'reason' => 'Default'], $this->billingUser);
        $workflow = app(InvoiceWorkflowService::class);
        $invoice = $workflow->createDraft(['facility_id' => $this->facility->id, 'currency' => 'NGN'], $this->patient, $this->billingUser);
        $workflow->addServiceLine($invoice, $this->service, ['quantity' => 1], $this->billingUser);
        $workflow->issue($invoice->fresh(), $this->billingUser);
        $workflow->transition($invoice->fresh(), 'void', $this->billingUser, 'Incorrect visit');
        $replacement = $workflow->replacementDraft($invoice->fresh(), $this->billingUser);

        $this->assertSame('voided', $invoice->refresh()->status);
        $this->assertSame($invoice->id, $replacement->replaces_invoice_id);
        $this->assertSame($replacement->id, $invoice->replaced_by_invoice_id);
        $this->assertDatabaseHas('invoice_events', ['invoice_id' => $invoice->id, 'action' => 'void']);
        $this->assertDatabaseHas('audit_events', ['action' => 'invoices.void']);
    }

    public function test_concurrent_safe_invoice_numbering_is_sequential(): void
    {
        app(ServicePricingService::class)->createPrice($this->service, ['currency' => 'NGN', 'amount_minor' => 10000, 'effective_from' => '2026-01-01', 'reason' => 'Default'], $this->billingUser);
        $workflow = app(InvoiceWorkflowService::class);
        $first = $workflow->createDraft(['facility_id' => $this->facility->id, 'currency' => 'NGN'], $this->patient, $this->billingUser);
        $second = $workflow->createDraft(['facility_id' => $this->facility->id, 'currency' => 'NGN'], $this->patient, $this->billingUser);
        $workflow->addServiceLine($first, $this->service, ['quantity' => 1], $this->billingUser);
        $workflow->addServiceLine($second, $this->service, ['quantity' => 1], $this->billingUser);
        $workflow->issue($first->fresh(), $this->billingUser);
        $workflow->issue($second->fresh(), $this->billingUser);

        $this->assertSame('INV-'.now()->format('Y').'-0001', $first->refresh()->invoice_number);
        $this->assertSame('INV-'.now()->format('Y').'-0002', $second->refresh()->invoice_number);
    }

    public function test_authorization_scoping_and_pages(): void
    {
        $viewer = $this->staffUser(['billing.catalogue.view', 'invoices.view'], 'receptionist');
        $this->actingAs($viewer)->post('/admin/billing/categories', ['name' => 'Denied', 'code' => 'DEN'])->assertForbidden();

        $this->actingAs($this->billingUser)->get('/admin/billing/catalogue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Billing/Catalogue')->has('services')->has('categories'));

        $invoice = app(InvoiceWorkflowService::class)->createDraft(['facility_id' => $this->facility->id, 'currency' => 'NGN'], $this->patient, $this->billingUser);
        $this->actingAs($this->billingUser)->get("/admin/billing/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Billing/InvoiceShow')->has('invoice.lines'));

        $otherHospital = Hospital::factory()->create();
        $otherPatient = Patient::create($this->patientPayload(['hospital_id' => $otherHospital->id, 'hospital_number' => 'OTHER']));
        $otherInvoice = Invoice::create(['hospital_id' => $otherHospital->id, 'patient_id' => $otherPatient->id, 'status' => 'draft', 'currency' => 'NGN', 'created_by' => $this->billingUser->id]);
        $this->actingAs($this->billingUser)->get("/admin/billing/invoices/{$otherInvoice->id}")->assertForbidden();
    }

    private function patient(): Patient
    {
        $patient = Patient::create($this->patientPayload());
        $patient->phone = '08030000000';
        $patient->save();

        return $patient;
    }

    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->billingUser?->id ?? User::factory()->create()->id,
            'hospital_number' => 'PAT-BILL',
            'first_name' => 'Bola',
            'last_name' => 'Billing',
            'date_of_birth' => '1991-01-01',
            'sex' => 'female',
            'status' => 'active',
        ], $overrides);
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
