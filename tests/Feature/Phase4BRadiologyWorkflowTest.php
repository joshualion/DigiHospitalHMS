<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\RadiologyModality;
use App\Models\RadiologyRequest;
use App\Models\RadiologyStudy;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Services\RadiologyWorkflowService;
use App\Services\ServicePricingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase4BRadiologyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private User $doctor;

    private User $radiologyUser;

    private User $approver;

    private Patient $patient;

    private Visit $visit;

    private ClinicalEncounter $encounter;

    private RadiologyModality $modality;

    private RadiologyStudy $study;

    private BillableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        foreach ([['key' => 'radiology_request_number', 'prefix' => 'RAD'], ['key' => 'radiology_accession_number', 'prefix' => 'RAC'], ['key' => 'invoice_number', 'prefix' => 'INV']] as $sequence) {
            NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => $sequence['key'], 'label' => $sequence['key'], 'prefix' => $sequence['prefix'], 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        }

        $this->doctor = $this->staffUser(['radiology.requests.view', 'radiology.requests.order', 'patients.view', 'encounters.view', 'invoices.create', 'billing.catalogue.view'], 'doctor');
        $this->radiologyUser = $this->staffUser(['radiology.catalogue.view', 'radiology.catalogue.manage', 'radiology.requests.view', 'radiology.schedule.manage', 'radiology.perform', 'radiology.reports.write', 'radiology.reports.verify', 'radiology.attachments.manage'], 'radiology-staff');
        $this->approver = $this->staffUser(['radiology.requests.view', 'radiology.reports.approve', 'radiology.reports.amend', 'radiology.attachments.manage'], 'radiology-staff');

        $this->patient = $this->patient();
        $this->visit = Visit::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $this->doctor->id, 'checked_in_at' => now()]);
        $this->encounter = ClinicalEncounter::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'visit_id' => $this->visit->id, 'responsible_clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $this->doctor->id, 'started_at' => now()]);

        $category = BillableServiceCategory::create(['hospital_id' => $this->hospital->id, 'name' => 'Radiology', 'code' => 'RAD', 'is_active' => true]);
        $this->service = BillableService::create(['hospital_id' => $this->hospital->id, 'billable_service_category_id' => $category->id, 'code' => 'XR-CFG', 'name' => 'Configured imaging billable service', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => true, 'is_active' => true]);
        app(ServicePricingService::class)->createPrice($this->service, ['currency' => 'NGN', 'amount_minor' => 25000, 'effective_from' => '2026-01-01', 'reason' => 'Radiology configured price'], $this->radiologyUser);

        $this->modality = RadiologyModality::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'XR', 'name' => 'Configured X-ray', 'description' => 'Professionally configured modality', 'is_active' => true]);
        $this->study = RadiologyStudy::create(['hospital_id' => $this->hospital->id, 'radiology_modality_id' => $this->modality->id, 'billable_service_id' => $this->service->id, 'code' => 'XR-CHEST', 'name' => 'Configured chest X-ray', 'description' => 'Professional configuration required', 'preparation_acknowledgements' => ['Configured preparation'], 'safety_screening_acknowledgements' => ['Configured safety acknowledgement'], 'requires_professional_validation' => true, 'is_active' => true]);
    }

    public function test_request_numbering_studies_and_billing_integration(): void
    {
        $request = $this->radiologyRequest();

        $this->assertSame('RAD-'.now()->format('Y').'-0001', $request->request_number);
        $this->assertSame('RAC-'.now()->format('Y').'-0001', $request->accession_number);
        $this->assertCount(1, $request->studies);
        $this->assertNotNull($request->invoice_id);
        $this->assertSame(25000, $request->invoice->refresh()->total_minor);
        $this->assertDatabaseHas('radiology_events', ['action' => 'radiology.requested']);
        $this->assertDatabaseHas('patient_activity_events', ['action' => 'radiology.requested']);
    }

    public function test_scheduling_conflicts_and_request_lifecycle(): void
    {
        $workflow = app(RadiologyWorkflowService::class);
        $first = $this->radiologyRequest();
        $second = $this->radiologyRequest();
        $slot = now()->addDay()->setTime(10, 0);

        $workflow->schedule($first, ['scheduled_at' => $slot, 'room' => 'XR-1', 'equipment' => 'X-ray A', 'assigned_staff_id' => $this->radiologyUser->staffProfile->id], $this->radiologyUser);

        try {
            $workflow->schedule($second, ['scheduled_at' => $slot, 'room' => 'XR-1', 'equipment' => 'X-ray B', 'assigned_staff_id' => $this->approver->staffProfile->id], $this->radiologyUser);
            $this->fail('Expected schedule conflict was not raised.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $workflow->transition($first->fresh(), 'arrive', $this->radiologyUser);
        $workflow->transition($first->fresh(), 'perform', $this->radiologyUser, notes: 'Study completed.');
        $workflow->transition($first->fresh(), 'reporting', $this->radiologyUser);

        $this->assertSame('reporting', $first->refresh()->status);
        $this->assertDatabaseHas('radiology_events', ['action' => 'radiology.perform']);
    }

    public function test_report_verification_approval_critical_communication_release_and_amendment(): void
    {
        $request = $this->performedRequest();
        $workflow = app(RadiologyWorkflowService::class);

        $report = $workflow->saveReport($request, ['findings' => 'Opacity described by radiologist.', 'impression' => 'Configured report impression.', 'recommendations' => 'Clinical correlation.', 'reporting_radiologist_id' => $this->radiologyUser->staffProfile->id, 'has_critical_finding' => true, 'critical_finding_notes' => 'Critical communication required.'], $this->radiologyUser);
        $workflow->verifyReport($report->fresh(), $this->radiologyUser);
        $workflow->approveReport($report->fresh(), $this->approver);
        $communication = $workflow->communicateCritical($report->fresh(), ['communicated_to' => 'Ordering clinician', 'method' => 'phone', 'notes' => 'Called responsible clinician.'], $this->approver);
        $workflow->acknowledgeCritical($communication->fresh(), $this->approver, 'Acknowledged and escalated.');
        $workflow->releaseReport($report->fresh(), $this->approver);
        $workflow->amendReport($report->fresh(), ['reason' => 'Clarified wording', 'content' => 'Append-only radiology amendment.'], $this->approver);

        $this->assertSame('released', $request->refresh()->status);
        $this->assertSame('released', $report->refresh()->status);
        $this->assertDatabaseHas('radiology_critical_communications', ['id' => $communication->id, 'acknowledged_by' => $this->approver->id]);
        $this->assertDatabaseHas('radiology_report_amendments', ['radiology_report_id' => $report->id, 'reason' => 'Clarified wording']);
        $this->actingAs($this->approver)->get("/admin/radiology/requests/{$request->id}/report")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Radiology/Report')->has('radiologyRequest.report'));
    }

    public function test_private_attachments_require_clearance_and_authorization(): void
    {
        Storage::fake('local');
        $request = $this->performedRequest();
        $workflow = app(RadiologyWorkflowService::class);
        $report = $workflow->saveReport($request, ['findings' => 'Attachment finding.', 'impression' => 'Attachment impression.', 'reporting_radiologist_id' => $this->radiologyUser->staffProfile->id], $this->radiologyUser);
        $attachment = $workflow->uploadAttachment($request->fresh(), UploadedFile::fake()->image('support.jpg'), $this->radiologyUser, $report->fresh());

        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($this->radiologyUser)->get("/admin/radiology/attachments/{$attachment->id}/download")->assertNotFound();

        $workflow->clearAttachment($attachment->fresh(), $this->radiologyUser);
        $this->actingAs($this->radiologyUser)->get("/admin/radiology/attachments/{$attachment->id}/download")->assertOk();

        $otherHospitalUser = User::factory()->create(['access_level' => 'admin']);
        $otherHospitalUser->syncRoles(['radiology-staff']);
        $otherHospitalUser->givePermissionTo(['radiology.requests.view']);
        StaffProfile::factory()->create(['user_id' => $otherHospitalUser->id, 'hospital_id' => Hospital::factory()->create()->id, 'is_active' => true]);
        $this->actingAs($otherHospitalUser)->get("/admin/radiology/attachments/{$attachment->id}/download")->assertForbidden();

        $workflow->verifyReport($report->fresh(), $this->radiologyUser);
        $workflow->approveReport($report->fresh(), $this->approver);
        $workflow->releaseReport($report->fresh(), $this->approver);

        $this->expectException(HttpException::class);
        $workflow->retireAttachment($attachment->fresh(), $this->approver, 'Attempt after release');
    }

    public function test_authorization_scoping_pages_and_timeline_visibility(): void
    {
        $viewer = $this->staffUser(['radiology.requests.view'], 'nurse');
        $this->actingAs($viewer)->post('/admin/radiology/requests', ['patient_id' => $this->patient->id])->assertForbidden();

        $this->actingAs($this->radiologyUser)->get('/admin/radiology/catalogue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Radiology/Catalogue')->has('studies')->has('modalities'));
        $this->actingAs($this->radiologyUser)->get('/admin/radiology/requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Radiology/Requests')->has('requests')->has('studies'));

        $otherHospital = Hospital::factory()->create();
        $otherFacility = Facility::factory()->create(['hospital_id' => $otherHospital->id, 'status' => 'active']);
        $otherPatient = Patient::create($this->patientPayload(['hospital_id' => $otherHospital->id, 'registration_facility_id' => $otherFacility->id, 'hospital_number' => 'OTHER-RAD']));
        $otherRequest = RadiologyRequest::create(['hospital_id' => $otherHospital->id, 'facility_id' => $otherFacility->id, 'patient_id' => $otherPatient->id, 'ordered_by' => $this->doctor->id, 'request_number' => 'OTHER-RAD', 'accession_number' => 'OTHER-RAC', 'status' => 'ordered', 'priority' => 'routine', 'clinical_indication' => 'Other hospital', 'ordered_at' => now()]);
        $this->actingAs($this->radiologyUser)->get("/admin/radiology/requests/{$otherRequest->id}")->assertForbidden();

        $request = $this->performedRequest();
        $workflow = app(RadiologyWorkflowService::class);
        $report = $workflow->saveReport($request, ['findings' => 'Timeline finding.', 'impression' => 'Timeline impression.', 'reporting_radiologist_id' => $this->radiologyUser->staffProfile->id], $this->radiologyUser);

        $this->actingAs($this->doctor)->get("/admin/encounters/{$this->encounter->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('timeline', fn ($timeline) => collect($timeline)->where('type', 'radiology')->isEmpty()));

        $workflow->verifyReport($report->fresh(), $this->radiologyUser);
        $workflow->approveReport($report->fresh(), $this->approver);
        $this->actingAs($this->doctor)->get("/admin/encounters/{$this->encounter->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('timeline', fn ($timeline) => collect($timeline)->where('type', 'radiology')->isNotEmpty()));
    }

    private function radiologyRequest(): RadiologyRequest
    {
        return app(RadiologyWorkflowService::class)->order([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'visit_id' => $this->visit->id,
            'clinical_encounter_id' => $this->encounter->id,
            'radiology_study_ids' => [$this->study->id],
            'priority' => 'routine',
            'clinical_indication' => 'Configured clinical indication for imaging.',
            'currency' => 'NGN',
        ], $this->doctor);
    }

    private function performedRequest(): RadiologyRequest
    {
        $workflow = app(RadiologyWorkflowService::class);
        $request = $this->radiologyRequest();
        $workflow->schedule($request, ['scheduled_at' => now()->addDay()->setTime(11, 0), 'room' => 'XR-2', 'equipment' => 'X-ray B', 'assigned_staff_id' => $this->radiologyUser->staffProfile->id], $this->radiologyUser);
        $workflow->transition($request->fresh(), 'arrive', $this->radiologyUser);
        $workflow->transition($request->fresh(), 'perform', $this->radiologyUser, notes: 'Performed.');

        return $request->fresh();
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
        return array_merge(['hospital_id' => $this->hospital->id, 'registration_facility_id' => $this->facility->id, 'registered_by' => $this->doctor?->id ?? User::factory()->create()->id, 'hospital_number' => 'PAT-RAD', 'first_name' => 'Radiology', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female', 'status' => 'active'], $overrides);
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
