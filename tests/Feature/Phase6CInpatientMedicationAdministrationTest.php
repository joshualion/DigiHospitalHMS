<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\BedClass;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\EmarAmendment;
use App\Models\EmarEvent;
use App\Models\EmarSchedule;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InpatientChart;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionWorkflowService;
use App\Services\EmarWorkflowService;
use App\Services\InpatientChartWorkflowService;
use App\Services\InventoryLedgerService;
use App\Services\PrescriptionWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6CInpatientMedicationAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private Department $department;

    private User $doctor;

    private User $nurse;

    private User $pharmacist;

    private Patient $patient;

    private InpatientChart $chart;

    private InventoryLocation $pharmacy;

    private InventoryBatch $batch;

    private InventoryUnit $unit;

    private InventoryItem $medicine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->hospital = Hospital::create(['legal_name' => 'Phase 6C Hospital', 'display_name' => 'Phase 6C', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
        $this->facility = Facility::create(['hospital_id' => $this->hospital->id, 'code' => 'P6C', 'name' => 'Phase 6C Facility', 'facility_type' => 'hospital', 'status' => 'active', 'is_primary' => true]);
        $this->department = Department::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'MED', 'name' => 'Medicine', 'category' => 'clinical', 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        foreach ([['admission_number', 'ADM'], ['prescription_number', 'RX']] as [$key, $prefix]) {
            NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => $key, 'label' => $key, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        }
        $this->doctor = $this->user('doctor', ['admissions.view', 'admissions.request', 'admissions.approve', 'admissions.manage', 'inpatient.view', 'inpatient.document', 'prescriptions.view', 'prescriptions.create', 'prescriptions.sign', 'emar.view']);
        $this->nurse = $this->user('nurse', ['inpatient.view', 'emar.view', 'emar.administer', 'emar.amend']);
        $this->pharmacist = $this->user('pharmacist', ['prescriptions.view', 'prescriptions.review', 'prescriptions.dispense', 'inventory.stock.receive']);
        $this->patient = Patient::create(['hospital_id' => $this->hospital->id, 'registration_facility_id' => $this->facility->id, 'registered_by' => $this->doctor->id, 'hospital_number' => 'P6C-001', 'status' => 'active', 'first_name' => 'Medication', 'last_name' => 'Patient', 'sex' => 'female']);
        PatientAllergy::create(['hospital_id' => $this->hospital->id, 'patient_id' => $this->patient->id, 'substance' => 'Configured allergy', 'severity' => 'high', 'status' => 'active', 'recorded_by' => $this->doctor->id, 'recorded_at' => now()]);
        $this->setupInventory();
        $this->chart = $this->activeChart();
    }

    public function test_regular_stat_and_prn_scheduling_and_administration(): void
    {
        $rx = $this->reviewedDispensedPrescription();
        $workflow = app(EmarWorkflowService::class);
        $workflow->syncSchedules($this->chart, $this->nurse);

        $this->assertGreaterThanOrEqual(3, EmarSchedule::where('inpatient_chart_id', $this->chart->id)->count());
        $regular = EmarSchedule::where('order_type', 'regular')->firstOrFail();
        $stat = EmarSchedule::where('order_type', 'stat')->firstOrFail();
        $prn = EmarSchedule::where('order_type', 'prn')->firstOrFail();

        $admin = $workflow->administer($regular, $this->payload(['outcome' => 'administered']), $this->nurse);
        $this->assertSame($this->batch->id, $admin->inventory_batch_id);
        $this->assertSame('2.0000', $workflow->remainingDispensedQuantity($rx->items()->where('medication_order_type', 'regular')->first()));

        try {
            $workflow->administer($regular->fresh(), $this->payload(['outcome' => 'administered']), $this->nurse);
            $this->fail('Duplicate administration should fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $workflow->administer($stat, $this->payload(['outcome' => 'held', 'reason' => 'Clinician requested hold']), $this->nurse);
        $workflow->administer($prn, $this->payload(['outcome' => 'administered', 'prn_indication' => 'Patient requested PRN dose', 'prn_response' => 'Response pending']), $this->nurse);
        $this->assertDatabaseHas('emar_administrations', ['outcome' => 'held', 'reason' => 'Clinician requested hold']);
        $this->assertDatabaseHas('patient_activity_events', ['patient_id' => $this->patient->id, 'action' => 'emar.administration_recorded']);
    }

    public function test_quantity_discontinued_discharge_reason_and_correction_protection(): void
    {
        $rx = $this->reviewedDispensedPrescription();
        $workflow = app(EmarWorkflowService::class);
        $workflow->syncSchedules($this->chart, $this->nurse);
        $schedule = EmarSchedule::where('order_type', 'regular')->firstOrFail();

        try {
            $workflow->administer($schedule, $this->payload(['outcome' => 'refused', 'reason' => '']), $this->nurse);
            $this->fail('Refused doses require a reason.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        try {
            $workflow->administer($schedule, $this->payload(['outcome' => 'administered', 'quantity_administered' => 99]), $this->nurse);
            $this->fail('Administration beyond dispensed stock should fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $admin = $workflow->administer($schedule, $this->payload(['outcome' => 'administered']), $this->nurse);
        $workflow->amend($admin, ['reason' => 'Late documentation correction', 'content' => 'Corrected timing note'], $this->nurse);
        $this->assertTrue(EmarAmendment::where('emar_administration_id', $admin->id)->exists());
        $this->assertTrue(EmarEvent::where('action', 'emar.amended')->exists());

        $next = EmarSchedule::where('order_type', 'stat')->firstOrFail();
        $rx->forceFill(['status' => 'discontinued'])->save();
        try {
            $workflow->administer($next, $this->payload(['outcome' => 'administered']), $this->nurse);
            $this->fail('Discontinued prescriptions should not administer.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $rx->forceFill(['status' => 'completed'])->save();
        $this->chart->admission->forceFill(['status' => 'discharged', 'discharged_at' => now()])->save();
        try {
            $workflow->administer($next->fresh(), $this->payload(['outcome' => 'administered']), $this->nurse);
            $this->fail('Discharged admissions should not administer.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_authorization_cross_hospital_isolation_and_pages(): void
    {
        $this->reviewedDispensedPrescription();
        $this->actingAs($this->nurse)->get('/admin/emar')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Emar/Index')->has('charts')->has('doses'));
        $this->actingAs($this->nurse)->get("/admin/emar/charts/{$this->chart->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Emar/Chart')->has('chart.patient.allergies')->has('doses'));
        $unauthorized = $this->user('cashier', ['patients.view']);
        $this->actingAs($unauthorized)->get('/admin/emar')->assertForbidden();

        $otherHospital = Hospital::create(['legal_name' => 'Other 6C', 'display_name' => 'Other 6C', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos']);
        $otherSchedule = EmarSchedule::create(['hospital_id' => $otherHospital->id, 'facility_id' => $this->facility->id, 'inpatient_chart_id' => $this->chart->id, 'admission_id' => $this->chart->admission_id, 'patient_id' => $this->patient->id, 'prescription_id' => Prescription::first()->id, 'prescription_item_id' => PrescriptionItem::first()->id, 'medicine_name' => 'Other med', 'dose' => 'dose', 'status' => 'pending']);
        $this->assertFalse($this->nurse->can('view', $otherSchedule));
    }

    private function reviewedDispensedPrescription(): Prescription
    {
        $prescriptions = app(PrescriptionWorkflowService::class);
        $rx = $prescriptions->createDraft(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'clinical_encounter_id' => $this->chart->clinical_encounter_id, 'items' => [
            ['inventory_item_id' => $this->medicine->id, 'inventory_unit_id' => $this->unit->id, 'dose' => 'Configured dose', 'route' => 'oral', 'frequency' => 'Clinician entered regular schedule', 'quantity' => 3, 'medication_order_type' => 'regular', 'scheduled_times' => [now()->format('H:i')], 'start_at' => now()->subHour(), 'end_at' => now()->addDay(), 'instructions' => 'Administer as charted'],
            ['inventory_item_id' => $this->medicine->id, 'inventory_unit_id' => $this->unit->id, 'dose' => 'Configured STAT dose', 'route' => 'oral', 'frequency' => 'STAT', 'quantity' => 1, 'medication_order_type' => 'stat', 'start_at' => now(), 'end_at' => now()->addDay()],
            ['inventory_item_id' => $this->medicine->id, 'inventory_unit_id' => $this->unit->id, 'dose' => 'Configured PRN dose', 'route' => 'oral', 'frequency' => 'PRN', 'quantity' => 1, 'is_prn' => true, 'medication_order_type' => 'prn', 'prn_instructions' => 'Only when clinically indicated'],
        ]], $this->doctor);
        $prescriptions->sign($rx, $this->doctor);
        $prescriptions->review($rx->fresh(), ['action' => 'approved'], $this->pharmacist);
        foreach ($rx->fresh('items')->items as $item) {
            $prescriptions->dispense($item, $this->pharmacy, $this->batch, $item->quantity, $this->pharmacist);
        }

        return $rx->refresh();
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['outcome' => 'administered', 'actual_at' => now(), 'quantity_administered' => 1, 'confirmation' => ['patient' => true, 'medication' => true, 'dose' => true, 'route' => true, 'timing' => true]];
    }

    private function setupInventory(): void
    {
        $this->unit = InventoryUnit::create(['hospital_id' => $this->hospital->id, 'code' => 'TAB', 'name' => 'Tablet', 'base_factor' => 1, 'is_active' => true]);
        $this->medicine = InventoryItem::create(['hospital_id' => $this->hospital->id, 'base_unit_id' => $this->unit->id, 'sku' => 'P6C-MED', 'type' => 'medicine', 'name' => 'Configured eMAR medicine', 'route' => 'oral', 'is_active' => true]);
        $this->pharmacy = InventoryLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'PHARM', 'name' => 'Pharmacy', 'type' => 'pharmacy', 'is_active' => true]);
        $this->batch = app(InventoryLedgerService::class)->receiveBatch(['hospital_id' => $this->hospital->id, 'inventory_location_id' => $this->pharmacy->id, 'inventory_item_id' => $this->medicine->id, 'inventory_unit_id' => $this->unit->id, 'batch_number' => 'P6C-BATCH', 'expiry_date' => now()->addMonth()->toDateString(), 'state' => 'available', 'quantity' => 20, 'reason' => 'Opening eMAR stock'], $this->pharmacist);
    }

    private function activeChart(): InpatientChart
    {
        $visit = Visit::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'patient_id' => $this->patient->id, 'clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $this->doctor->id, 'checked_in_at' => now()]);
        $encounter = ClinicalEncounter::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'patient_id' => $this->patient->id, 'visit_id' => $visit->id, 'responsible_clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $this->doctor->id, 'started_at' => now()]);
        $ward = Ward::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'code' => 'WARD', 'name' => 'Ward', 'status' => 'active']);
        $room = WardRoom::create(['hospital_id' => $this->hospital->id, 'ward_id' => $ward->id, 'code' => 'R1', 'name' => 'Room', 'status' => 'active']);
        $class = BedClass::create(['hospital_id' => $this->hospital->id, 'code' => 'GEN', 'name' => 'General', 'is_active' => true]);
        $bed = Bed::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $class->id, 'code' => 'B1', 'label' => 'Bed 1', 'state' => 'available']);
        $admissions = app(AdmissionWorkflowService::class);
        $admission = $admissions->request(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'visit_id' => $visit->id, 'clinical_encounter_id' => $encounter->id, 'department_id' => $this->department->id, 'reason' => 'Medication administration admission'], $this->doctor);
        $admissions->approve($admission, $this->doctor);
        $admission = $admissions->admit($admission->fresh(), $bed, $this->doctor);

        return app(InpatientChartWorkflowService::class)->chartForAdmission($admission, $this->doctor);
    }

    private function user(string $role, array $permissions): User
    {
        $user = User::factory()->create(['access_level' => $role, 'status' => 'active']);
        $user->syncRoles([$role]);
        $user->givePermissionTo($permissions);
        StaffProfile::create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'staff_number' => strtoupper($role).'-'.substr(md5((string) $user->id), 0, 6), 'job_title' => $role, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);

        return $user->load('staffProfile', 'roles');
    }
}
