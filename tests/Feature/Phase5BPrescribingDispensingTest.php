<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\PatientAllergy;
use App\Models\Prescription;
use App\Models\PrescriptionDispense;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Services\InventoryLedgerService;
use App\Services\PrescriptionWorkflowService;
use App\Services\ServicePricingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase5BPrescribingDispensingTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private User $doctor;

    private User $pharmacist;

    private Patient $patient;

    private ClinicalEncounter $encounter;

    private InventoryUnit $each;

    private InventoryItem $medicine;

    private InventoryLocation $pharmacy;

    private InventoryBatch $earlyBatch;

    private InventoryBatch $lateBatch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => 'prescription_number', 'label' => 'Prescription', 'prefix' => 'RX', 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => 'invoice_number', 'label' => 'Invoice', 'prefix' => 'INV', 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);

        $this->doctor = $this->staffUser(['prescriptions.view', 'prescriptions.create', 'prescriptions.sign', 'patients.view', 'encounters.view', 'inventory.view'], 'doctor');
        $this->pharmacist = $this->staffUser(['prescriptions.view', 'prescriptions.review', 'prescriptions.dispense', 'prescriptions.reverse', 'inventory.view', 'inventory.stock.receive', 'billing.catalogue.view', 'invoices.create'], 'pharmacist');
        $this->patient = Patient::create(['hospital_id' => $this->hospital->id, 'registration_facility_id' => $this->facility->id, 'registered_by' => $this->doctor->id, 'hospital_number' => 'P5B-PAT', 'first_name' => 'Pharmacy', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female', 'status' => 'active']);
        PatientAllergy::create(['hospital_id' => $this->hospital->id, 'patient_id' => $this->patient->id, 'substance' => 'Configured allergy', 'severity' => 'high', 'status' => 'active', 'recorded_by' => $this->doctor->id, 'recorded_at' => now()]);
        PatientAlert::create(['hospital_id' => $this->hospital->id, 'patient_id' => $this->patient->id, 'title' => 'Configured alert', 'severity' => 'medium', 'status' => 'active', 'recorded_by' => $this->doctor->id, 'recorded_at' => now()]);
        $visit = Visit::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $this->doctor->id, 'checked_in_at' => now()]);
        $this->encounter = ClinicalEncounter::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'visit_id' => $visit->id, 'responsible_clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $this->doctor->id, 'started_at' => now()]);

        $category = BillableServiceCategory::create(['hospital_id' => $this->hospital->id, 'code' => 'MED', 'name' => 'Medicine', 'is_active' => true]);
        $service = BillableService::create(['hospital_id' => $this->hospital->id, 'billable_service_category_id' => $category->id, 'code' => 'MED-P5B', 'name' => 'Configured medicine charge', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => true, 'is_active' => true]);
        app(ServicePricingService::class)->createPrice($service, ['currency' => 'NGN', 'amount_minor' => 500, 'effective_from' => '2026-01-01', 'reason' => 'Medicine price'], $this->pharmacist);
        $this->each = InventoryUnit::create(['hospital_id' => $this->hospital->id, 'code' => 'TAB', 'name' => 'Tablet', 'base_factor' => 1, 'is_active' => true]);
        $this->medicine = InventoryItem::create(['hospital_id' => $this->hospital->id, 'base_unit_id' => $this->each->id, 'billable_service_id' => $service->id, 'sku' => 'MED-P5B', 'type' => 'medicine', 'name' => 'Configured medicine', 'route' => 'oral', 'reorder_level' => 5, 'is_active' => true]);
        $this->pharmacy = InventoryLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'PHARM', 'name' => 'Pharmacy', 'type' => 'pharmacy', 'is_active' => true]);
        $this->earlyBatch = $this->receive('EARLY', 12, now()->addMonth()->toDateString());
        $this->lateBatch = $this->receive('LATE', 20, now()->addMonths(6)->toDateString());
    }

    public function test_prescription_signing_immutability_allergy_visibility_review_and_billing(): void
    {
        $rx = $this->signedPrescription(10);
        $this->assertSame('RX-'.now()->format('Y').'-0001', $rx->prescription_number);
        $this->assertSame('signed', $rx->status);

        $this->expectException(HttpException::class);
        app(PrescriptionWorkflowService::class)->sign($rx->fresh(), $this->doctor);
    }

    public function test_review_billing_and_page_allergy_visibility(): void
    {
        $rx = $this->signedPrescription(10);
        $workflow = app(PrescriptionWorkflowService::class);
        $workflow->review($rx, ['action' => 'clarification_requested', 'reason' => 'Clarify duration'], $this->pharmacist);
        $workflow->review($rx->fresh(), ['action' => 'approved'], $this->pharmacist);
        $workflow->bill($rx->fresh(), $this->pharmacist, 'NGN');

        $this->assertSame(5000, $rx->refresh()->invoice->total_minor);
        $this->actingAs($this->pharmacist)->get("/admin/pharmacy/prescriptions/{$rx->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Pharmacy/PrescriptionShow')->has('prescription.patient.allergies')->has('prescription.patient.alerts'));
    }

    public function test_partial_full_dispensing_fefo_invalid_batch_return_and_reversal(): void
    {
        $rx = $this->signedPrescription(10);
        $workflow = app(PrescriptionWorkflowService::class);
        $workflow->review($rx, ['action' => 'approved'], $this->pharmacist);
        $item = $rx->items()->first();
        $this->assertSame('EARLY', app(InventoryLedgerService::class)->fefoBatches($this->medicine, $this->pharmacy)->first()->batch->batch_number);

        $dispense = $workflow->dispense($item, $this->pharmacy, $this->earlyBatch, 4, $this->pharmacist, 'Take as directed');
        $this->assertSame('4.0000', $item->refresh()->dispensed_quantity);
        $workflow->dispense($item->fresh(), $this->pharmacy, $this->earlyBatch, 6, $this->pharmacist, 'Complete course');
        $this->assertSame('completed', $rx->refresh()->status);
        $secondDispense = PrescriptionDispense::where('prescription_id', $rx->id)->where('action', 'dispense')->latest('id')->first();

        $workflow->returnDispense($dispense, $this->pharmacist, 'Patient returned sealed stock');
        $this->assertSame('signed', $rx->refresh()->status);
        $reversal = $workflow->reverseDispense($secondDispense, $this->pharmacist, 'Correct duplicate posting');
        $this->assertSame('reversal', $reversal->action);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'reversal']);

        $this->expectException(HttpException::class);
        $workflow->returnDispense($secondDispense->fresh(), $this->pharmacist, 'Duplicate correction');
    }

    public function test_invalid_batch_and_overdispense_are_blocked(): void
    {
        $rx = $this->signedPrescription(5);
        $workflow = app(PrescriptionWorkflowService::class);
        $workflow->review($rx, ['action' => 'approved'], $this->pharmacist);
        app(InventoryLedgerService::class)->setBatchState($this->lateBatch, 'recalled', $this->pharmacist, 'Recall');

        try {
            $workflow->dispense($rx->items()->first(), $this->pharmacy, $this->lateBatch, 1, $this->pharmacist);
            $this->fail('Recalled batch should not dispense.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        try {
            $workflow->dispense($rx->items()->first(), $this->pharmacy, $this->earlyBatch, 99, $this->pharmacist);
            $this->fail('Overdispense should not pass.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_authorization_and_index_page(): void
    {
        $viewer = $this->staffUser(['prescriptions.view'], 'nurse');
        $this->actingAs($viewer)->post('/admin/pharmacy/prescriptions', [])->assertForbidden();
        $this->actingAs($this->doctor)->get('/admin/pharmacy/prescriptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Pharmacy/Prescriptions')->has('prescriptions'));
    }

    private function signedPrescription(float $quantity): Prescription
    {
        $workflow = app(PrescriptionWorkflowService::class);
        $rx = $workflow->createDraft(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'clinical_encounter_id' => $this->encounter->id, 'items' => [['inventory_item_id' => $this->medicine->id, 'inventory_unit_id' => $this->each->id, 'dose' => 'Configured dose', 'frequency' => 'Configured frequency', 'duration' => 'Configured duration', 'quantity' => $quantity, 'instructions' => 'Configured instructions']]], $this->doctor);

        return $workflow->sign($rx, $this->doctor);
    }

    private function receive(string $batch, float $quantity, string $expiry): InventoryBatch
    {
        return app(InventoryLedgerService::class)->receiveBatch(['hospital_id' => $this->hospital->id, 'inventory_location_id' => $this->pharmacy->id, 'inventory_item_id' => $this->medicine->id, 'inventory_unit_id' => $this->each->id, 'batch_number' => $batch, 'expiry_date' => $expiry, 'state' => 'available', 'quantity' => $quantity, 'reason' => 'Opening pharmacy stock'], $this->pharmacist);
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
