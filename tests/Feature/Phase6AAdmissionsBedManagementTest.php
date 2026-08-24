<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionBedMovement;
use App\Models\AdmissionEvent;
use App\Models\Bed;
use App\Models\BedClass;
use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6AAdmissionsBedManagementTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private Department $department;

    private Patient $patient;

    private Visit $visit;

    private ClinicalEncounter $encounter;

    private User $doctor;

    private User $nurse;

    private Bed $bedA;

    private Bed $bedB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->hospital = Hospital::create(['legal_name' => 'Phase 6A Hospital', 'display_name' => 'Phase 6A', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
        $this->facility = Facility::create(['hospital_id' => $this->hospital->id, 'code' => 'P6A', 'name' => 'Phase 6A Facility', 'facility_type' => 'hospital', 'status' => 'active', 'is_primary' => true]);
        $this->department = Department::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'MED', 'name' => 'Medicine', 'category' => 'clinical', 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        foreach ([['admission_number', 'ADM'], ['invoice_number', 'INV']] as [$key, $prefix]) {
            NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => $key, 'label' => $key, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        }
        $this->doctor = $this->user('doctor', ['admissions.view', 'admissions.request', 'admissions.approve', 'admissions.manage', 'admissions.discharge', 'admissions.discharge.override', 'invoices.create']);
        $this->nurse = $this->user('nurse', ['admissions.view', 'admissions.request', 'admissions.manage', 'admissions.discharge']);
        $this->patient = Patient::create(['hospital_id' => $this->hospital->id, 'registration_facility_id' => $this->facility->id, 'registered_by' => $this->doctor->id, 'hospital_number' => 'P6A-001', 'status' => 'active', 'first_name' => 'Admit', 'last_name' => 'Patient', 'sex' => 'female']);
        $this->visit = Visit::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'patient_id' => $this->patient->id, 'clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $this->doctor->id, 'checked_in_at' => now()]);
        $this->encounter = ClinicalEncounter::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'patient_id' => $this->patient->id, 'visit_id' => $this->visit->id, 'responsible_clinician_id' => $this->doctor->staffProfile->id, 'source' => 'outpatient', 'status' => 'in_progress', 'started_by' => $this->doctor->id, 'started_at' => now()]);
        [$this->bedA, $this->bedB] = $this->beds();
    }

    public function test_request_approval_allocation_double_booking_transfer_and_history(): void
    {
        $workflow = app(AdmissionWorkflowService::class);
        $admission = $this->approvedAdmission();
        $workflow->admit($admission, $this->bedA, $this->doctor, ['admitted_at' => now()->subDays(2)]);

        $this->assertStringStartsWith('ADM-', $admission->refresh()->admission_number);
        $this->assertSame('occupied', $this->bedA->refresh()->state);
        $this->expectException(HttpException::class);
        $workflow->admit($this->approvedAdmission(), $this->bedA->fresh(), $this->doctor);
    }

    public function test_transfer_bed_states_discharge_override_billing_and_timeline(): void
    {
        $workflow = app(AdmissionWorkflowService::class);
        $admission = $this->approvedAdmission(clearanceRequired: true);
        $workflow->admit($admission, $this->bedA, $this->doctor, ['admitted_at' => now()->subDays(2)]);
        $workflow->transfer($admission->fresh(), $this->bedB, $this->doctor, 'Needs monitored bed');
        $this->assertSame('cleaning', $this->bedA->refresh()->state);
        $this->assertSame('occupied', $this->bedB->refresh()->state);

        try {
            $workflow->discharge($admission->fresh(), $this->nurse, ['discharge_destination' => 'home']);
            $this->fail('Expected unresolved administrative clearance to block discharge.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $workflow->discharge($admission->fresh(), $this->doctor, ['discharge_destination' => 'home', 'discharge_outcome' => 'stable', 'override' => true, 'override_reason' => 'Senior clinician approved pending admin check']);
        $this->assertSame('discharged', $admission->refresh()->status);
        $this->assertSame('cleaning', $this->bedB->refresh()->state);
        $this->assertNotNull($admission->invoice_id);
        $this->assertDatabaseHas('invoice_lines', ['invoice_id' => $admission->invoice_id, 'service_code' => 'BED']);
        $this->assertGreaterThanOrEqual(2, AdmissionBedMovement::where('admission_id', $admission->id)->count());
        $this->assertDatabaseHas('patient_activity_events', ['patient_id' => $this->patient->id, 'action' => 'admission.discharged']);
    }

    public function test_bed_hold_release_maintenance_cancel_authorization_scoping_and_dashboard(): void
    {
        $workflow = app(AdmissionWorkflowService::class);
        $workflow->setBedState($this->bedA, 'reserved', $this->doctor, 'Temporary hold');
        $this->assertSame('reserved', $this->bedA->refresh()->state);
        $workflow->setBedState($this->bedA, 'available', $this->doctor, 'Released');
        $workflow->setBedState($this->bedA, 'maintenance', $this->doctor, 'Repair');
        $this->assertSame('maintenance', $this->bedA->refresh()->state);

        $admission = $this->approvedAdmission();
        $workflow->cancel($admission, $this->doctor, 'Patient declined admission');
        $this->assertSame('cancelled', $admission->refresh()->status);
        $this->assertTrue(AdmissionEvent::where('action', 'admissions.cancelled')->exists());

        $this->actingAs($this->doctor)->get('/admin/admissions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Admissions/Index')->has('admissions')->has('beds')->has('census'));

        $otherHospital = Hospital::create(['legal_name' => 'Other', 'display_name' => 'Other', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos']);
        $otherAdmission = Admission::create(['hospital_id' => $otherHospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'status' => 'requested', 'requested_by' => $this->doctor->id]);
        $this->assertFalse($this->doctor->can('view', $otherAdmission));
    }

    private function approvedAdmission(bool $clearanceRequired = false): Admission
    {
        $workflow = app(AdmissionWorkflowService::class);
        $admission = $workflow->request(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'visit_id' => $this->visit->id, 'clinical_encounter_id' => $this->encounter->id, 'department_id' => $this->department->id, 'attending_clinician_id' => $this->doctor->staffProfile->id, 'reason' => 'Needs inpatient care', 'provisional_diagnosis' => 'Configured diagnosis', 'administrative_clearance_required' => $clearanceRequired], $this->doctor);
        $workflow->approve($admission, $this->doctor);

        return $admission->refresh();
    }

    private function beds(): array
    {
        $category = BillableServiceCategory::create(['hospital_id' => $this->hospital->id, 'code' => 'ACCOM', 'name' => 'Accommodation', 'is_active' => true]);
        $service = BillableService::create(['hospital_id' => $this->hospital->id, 'billable_service_category_id' => $category->id, 'code' => 'BED', 'name' => 'General ward bed day', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => false, 'is_active' => true]);
        ServicePrice::create(['hospital_id' => $this->hospital->id, 'billable_service_id' => $service->id, 'facility_id' => $this->facility->id, 'currency' => 'NGN', 'amount_minor' => 150000, 'effective_from' => today()->subDay(), 'is_active' => true, 'created_by' => $this->doctor->id, 'reason' => 'Configured bed day']);
        $class = BedClass::create(['hospital_id' => $this->hospital->id, 'billable_service_id' => $service->id, 'code' => 'GEN', 'name' => 'General', 'is_active' => true]);
        $ward = Ward::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'code' => 'WARD', 'name' => 'Medical Ward', 'status' => 'active']);
        $room = WardRoom::create(['hospital_id' => $this->hospital->id, 'ward_id' => $ward->id, 'code' => 'R1', 'name' => 'Room 1', 'status' => 'active']);

        return [
            Bed::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $class->id, 'code' => 'A', 'label' => 'Bed A', 'state' => 'available']),
            Bed::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $class->id, 'code' => 'B', 'label' => 'Bed B', 'state' => 'available']),
        ];
    }

    private function user(string $role, array $permissions): User
    {
        $user = User::factory()->create(['access_level' => 'admin', 'status' => 'active']);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);
        StaffProfile::create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'staff_number' => strtoupper($role).'-'.substr(md5((string) $user->id), 0, 6), 'job_title' => $role, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);

        return $user->load('staffProfile', 'roles');
    }
}
