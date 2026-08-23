<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\LabReferenceRange;
use App\Models\LabRequest;
use App\Models\LabSpecimenType;
use App\Models\LabTest;
use App\Models\LabTestComponent;
use App\Models\LabTestProfile;
use App\Models\LabUnit;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Services\LaboratoryWorkflowService;
use App\Services\ServicePricingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase4ALaboratoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private User $doctor;

    private User $labUser;

    private User $approver;

    private Patient $patient;

    private Visit $visit;

    private ClinicalEncounter $encounter;

    private LabSpecimenType $blood;

    private LabUnit $unit;

    private LabTest $test;

    private LabTestComponent $component;

    private BillableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        foreach ([
            ['key' => 'lab_request_number', 'prefix' => 'LAB'],
            ['key' => 'lab_accession_number', 'prefix' => 'ACC'],
            ['key' => 'lab_specimen_number', 'prefix' => 'SPC'],
            ['key' => 'invoice_number', 'prefix' => 'INV'],
        ] as $sequence) {
            NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => $sequence['key'], 'label' => $sequence['key'], 'prefix' => $sequence['prefix'], 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        }

        $this->doctor = $this->staffUser(['lab.requests.view', 'lab.requests.order', 'patients.view', 'encounters.view', 'invoices.create', 'billing.catalogue.view'], 'doctor');
        $this->labUser = $this->staffUser(['lab.catalogue.view', 'lab.catalogue.manage', 'lab.requests.view', 'lab.specimens.manage', 'lab.results.enter', 'lab.results.verify'], 'laboratory-scientist');
        $this->approver = $this->staffUser(['lab.requests.view', 'lab.results.approve', 'lab.results.amend'], 'laboratory-scientist');
        $this->patient = $this->patient();
        $this->visit = Visit::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $this->doctor->id, 'checked_in_at' => now()]);
        $this->encounter = ClinicalEncounter::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'visit_id' => $this->visit->id, 'responsible_clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $this->doctor->id, 'started_at' => now()]);

        $category = BillableServiceCategory::create(['hospital_id' => $this->hospital->id, 'name' => 'Laboratory', 'code' => 'LAB', 'is_active' => true]);
        $this->service = BillableService::create(['hospital_id' => $this->hospital->id, 'billable_service_category_id' => $category->id, 'code' => 'LAB-FBC', 'name' => 'Configured lab billable service', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => true, 'is_active' => true]);
        app(ServicePricingService::class)->createPrice($this->service, ['currency' => 'NGN', 'amount_minor' => 15000, 'effective_from' => '2026-01-01', 'reason' => 'Lab smoke price'], $this->labUser);

        $this->blood = LabSpecimenType::create(['hospital_id' => $this->hospital->id, 'code' => 'BLOOD', 'name' => 'Blood', 'is_active' => true]);
        $this->unit = LabUnit::create(['hospital_id' => $this->hospital->id, 'code' => 'CFG', 'name' => 'Configured unit', 'is_active' => true]);
        $this->test = LabTest::create(['hospital_id' => $this->hospital->id, 'default_specimen_type_id' => $this->blood->id, 'billable_service_id' => $this->service->id, 'code' => 'FBC', 'name' => 'Configured full blood count', 'requires_approval' => true, 'is_active' => true]);
        $this->component = LabTestComponent::create(['hospital_id' => $this->hospital->id, 'lab_test_id' => $this->test->id, 'lab_unit_id' => $this->unit->id, 'code' => 'HB', 'name' => 'Configured haemoglobin', 'result_type' => 'numeric', 'sort_order' => 1, 'is_required' => true, 'is_active' => true]);
        LabReferenceRange::create(['hospital_id' => $this->hospital->id, 'lab_test_component_id' => $this->component->id, 'label' => 'Professionally configured test range', 'low_value' => 10, 'high_value' => 20, 'critical_low_value' => 5, 'critical_high_value' => 30, 'display_text' => 'Configured safe test range', 'requires_professional_validation' => true, 'is_active' => true]);
    }

    public function test_request_numbering_panels_and_billing_integration(): void
    {
        $profile = LabTestProfile::create(['hospital_id' => $this->hospital->id, 'code' => 'HAEM', 'name' => 'Haematology panel', 'is_active' => true]);
        $profile->tests()->sync([$this->test->id]);

        $request = app(LaboratoryWorkflowService::class)->order([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'visit_id' => $this->visit->id,
            'clinical_encounter_id' => $this->encounter->id,
            'lab_test_profile_ids' => [$profile->id],
            'priority' => 'urgent',
            'currency' => 'NGN',
        ], $this->doctor);

        $this->assertSame('LAB-'.now()->format('Y').'-0001', $request->request_number);
        $this->assertSame('ACC-'.now()->format('Y').'-0001', $request->accession_number);
        $this->assertCount(1, $request->tests);
        $this->assertNotNull($request->invoice_id);
        $this->assertSame(15000, $request->invoice->refresh()->total_minor);
        $this->assertDatabaseHas('lab_events', ['action' => 'lab.requested']);
        $this->assertDatabaseHas('patient_activity_events', ['action' => 'lab.requested']);
    }

    public function test_specimen_lifecycle_rejection_and_recollection_history(): void
    {
        $request = $this->labRequest();
        $workflow = app(LaboratoryWorkflowService::class);
        $specimen = $workflow->collectSpecimen($request, $this->blood, $this->labUser);
        $workflow->rejectSpecimen($specimen->fresh(), $this->labUser, 'Clotted specimen');
        $replacement = $workflow->collectSpecimen($request->fresh(), $this->blood, $this->labUser);
        $workflow->receiveSpecimen($replacement->fresh(), $this->labUser);

        $this->assertSame('rejected', $specimen->refresh()->status);
        $this->assertSame('received', $replacement->refresh()->status);
        $this->assertSame('SPC-'.now()->format('Y').'-0002', $replacement->label_number);
        $this->assertDatabaseHas('lab_events', ['action' => 'lab.specimen_rejected', 'reason' => 'Clotted specimen']);
    }

    public function test_result_types_flags_verification_approval_release_and_report_visibility(): void
    {
        $request = $this->labRequest();
        $workflow = app(LaboratoryWorkflowService::class);
        $result = $workflow->enterResult($request->tests()->first(), $this->component, ['numeric_value' => 40], $this->labUser);

        $this->assertSame('critical', $result->flag);
        $this->assertTrue($result->is_critical);
        $this->actingAs($this->labUser)->get("/admin/laboratory/requests/{$request->id}/report")->assertNotFound();

        $workflow->verifyResult($result->fresh(), $this->labUser);
        $this->expectException(HttpException::class);
        $workflow->approveResult($result->fresh(), $this->labUser);
    }

    public function test_approval_release_critical_acknowledgement_and_amendments_are_append_only(): void
    {
        $request = $this->labRequest();
        $workflow = app(LaboratoryWorkflowService::class);
        $result = $workflow->enterResult($request->tests()->first(), $this->component, ['numeric_value' => 40], $this->labUser);
        $workflow->verifyResult($result->fresh(), $this->labUser);
        $workflow->approveResult($result->fresh(), $this->approver);
        $workflow->acknowledgeCritical($result->fresh(), $this->approver, 'Called clinician and documented escalation.');
        $workflow->releaseRequest($request->fresh(), $this->approver);
        $workflow->amendReport($request->fresh(), ['reason' => 'Corrected interpretive comment', 'content' => 'Append-only correction note.'], $this->approver);

        $this->assertSame('released', $request->refresh()->status);
        $this->assertSame(40.0, (float) $result->refresh()->numeric_value);
        $this->assertDatabaseHas('lab_report_amendments', ['lab_request_id' => $request->id, 'reason' => 'Corrected interpretive comment']);
        $this->actingAs($this->approver)->get("/admin/laboratory/requests/{$request->id}/report")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Laboratory/Report')->has('labRequest.results'));
    }

    public function test_authorization_scoping_and_pages(): void
    {
        $request = $this->labRequest();
        $viewer = $this->staffUser(['lab.requests.view'], 'nurse');
        $this->actingAs($viewer)->post('/admin/laboratory/requests', ['patient_id' => $this->patient->id])->assertForbidden();

        $this->actingAs($this->labUser)->get('/admin/laboratory/catalogue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Laboratory/Catalogue')->has('tests')->has('profiles'));
        $this->actingAs($this->labUser)->get('/admin/laboratory/requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Laboratory/Requests')->has('requests')->has('labTests'));

        $otherHospital = Hospital::factory()->create();
        $otherPatient = Patient::create($this->patientPayload(['hospital_id' => $otherHospital->id, 'hospital_number' => 'OTHER']));
        $otherRequest = LabRequest::create(['hospital_id' => $otherHospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $otherPatient->id, 'ordered_by' => $this->doctor->id, 'request_number' => 'OTHER-LAB', 'accession_number' => 'OTHER-ACC', 'status' => 'ordered', 'priority' => 'routine', 'ordered_at' => now()]);
        $this->actingAs($this->labUser)->get("/admin/laboratory/requests/{$otherRequest->id}")->assertForbidden();

        $this->actingAs($this->labUser)->get("/admin/laboratory/requests/{$request->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Laboratory/RequestShow')->has('labRequest.tests'));
    }

    private function labRequest(): LabRequest
    {
        return app(LaboratoryWorkflowService::class)->order([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'visit_id' => $this->visit->id,
            'clinical_encounter_id' => $this->encounter->id,
            'lab_test_ids' => [$this->test->id],
            'priority' => 'routine',
            'currency' => 'NGN',
        ], $this->doctor);
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
        return array_merge(['hospital_id' => $this->hospital->id, 'registration_facility_id' => $this->facility->id, 'registered_by' => $this->doctor?->id ?? User::factory()->create()->id, 'hospital_number' => 'PAT-LAB', 'first_name' => 'Lab', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female', 'status' => 'active'], $overrides);
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
