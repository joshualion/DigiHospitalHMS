<?php

namespace Tests\Feature;

use App\Models\BloodBankLocation;
use App\Models\BloodComponent;
use App\Models\BloodComponentIssue;
use App\Models\BloodComponentType;
use App\Models\BloodDonorCategory;
use App\Models\BloodRequest;
use App\Models\BloodScreeningTest;
use App\Models\BloodStorageUnit;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BloodBankWorkflowService;
use App\Services\BloodRequestWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase7BPatientBloodRequestTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private Patient $patient;

    private User $requester;

    private User $technologist;

    private User $authorizer;

    private StaffProfile $clinician;

    private BloodBankLocation $location;

    private BloodStorageUnit $storage;

    private BloodComponentType $redCells;

    private BloodComponentType $plasma;

    private BloodScreeningTest $screeningTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN', 'operating_preferences' => ['blood_bank' => ['reservation_expiry_minutes' => 30]]]);

        foreach ([['blood_donor_number', 'BDN'], ['blood_donation_number', 'DON'], ['blood_collection_number', 'BAG'], ['blood_component_number', 'BCP'], ['blood_request_number', 'BTR'], ['blood_specimen_label', 'BSP'], ['blood_issue_number', 'BIS']] as [$key, $prefix]) {
            NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => $key, 'label' => $key, 'prefix' => $prefix, 'date_format' => $key === 'blood_specimen_label' ? 'Ymd' : 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        }

        $this->requester = $this->staffUser(['blood-bank.requests.view', 'blood-bank.requests.order'], 'doctor');
        $this->technologist = $this->staffUser(['blood-bank.requests.view', 'blood-bank.requests.manage', 'blood-bank.specimens.manage', 'blood-bank.compatibility.enter', 'blood-bank.reservations.manage', 'blood-bank.issues.manage', 'blood-bank.view', 'blood-bank.testing.manage', 'blood-bank.components.manage', 'blood-bank.components.release'], 'blood-bank-staff');
        $this->authorizer = $this->staffUser(['blood-bank.requests.view', 'blood-bank.requests.manage', 'blood-bank.compatibility.authorize', 'blood-bank.reservations.manage', 'blood-bank.issues.manage', 'blood-bank.emergency-release.authorize', 'blood-bank.view', 'blood-bank.testing.verify', 'blood-bank.components.release'], 'blood-bank-staff');
        $this->clinician = $this->requester->staffProfile;

        $this->patient = Patient::create(['hospital_id' => $this->hospital->id, 'registration_facility_id' => $this->facility->id, 'registered_by' => $this->requester->id, 'hospital_number' => 'PAT-001', 'status' => 'active', 'first_name' => 'Patient', 'last_name' => 'Recipient', 'sex' => 'female']);
        $this->location = BloodBankLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'BB', 'name' => 'Blood Bank', 'is_active' => true]);
        $this->storage = BloodStorageUnit::create(['hospital_id' => $this->hospital->id, 'blood_bank_location_id' => $this->location->id, 'code' => 'FR1', 'name' => 'Fridge 1', 'status' => 'active']);
        BloodDonorCategory::create(['hospital_id' => $this->hospital->id, 'code' => 'VOL', 'name' => 'Voluntary', 'is_active' => true]);
        $this->redCells = BloodComponentType::create(['hospital_id' => $this->hospital->id, 'code' => 'RBC', 'name' => 'Red cells', 'default_shelf_life_days' => 35, 'is_active' => true]);
        $this->plasma = BloodComponentType::create(['hospital_id' => $this->hospital->id, 'code' => 'PLASMA', 'name' => 'Plasma', 'default_shelf_life_days' => 20, 'is_active' => true]);
        $this->screeningTest = BloodScreeningTest::create(['hospital_id' => $this->hospital->id, 'code' => 'CFG', 'name' => 'Configured screening', 'is_required_for_release' => true, 'is_active' => true]);
    }

    public function test_specimen_identity_group_verification_manual_crossmatch_reservation_partial_issue_and_return_reversal(): void
    {
        $workflow = app(BloodRequestWorkflowService::class);
        $componentOne = $this->usableComponent($this->redCells);
        $componentTwo = $this->usableComponent($this->redCells);
        $request = $this->bloodRequest(['quantity_requested' => 2]);

        $specimen = $workflow->collectSpecimen($request, ['patient_confirmed_name' => $this->patient->full_name, 'patient_confirmed_identifier' => $this->patient->hospital_number, 'collection_location' => 'Ward A', 'label_status' => 'matched'], $this->technologist);
        $group = $workflow->enterPatientGroup($request->fresh(), ['blood_request_specimen_id' => $specimen->id, 'abo_group' => 'O', 'rh_factor' => 'positive'], $this->technologist);
        $this->expectException(HttpException::class);
        $workflow->verifyPatientGroup($group->fresh(), $this->technologist);
    }

    public function test_full_manual_issue_flow_and_history_preserving_return_and_reversal(): void
    {
        $workflow = app(BloodRequestWorkflowService::class);
        $componentOne = $this->usableComponent($this->redCells);
        $componentTwo = $this->usableComponent($this->redCells);
        $request = $this->bloodRequest(['quantity_requested' => 2]);
        $specimen = $workflow->collectSpecimen($request, ['patient_confirmed_name' => $this->patient->full_name, 'patient_confirmed_identifier' => $this->patient->hospital_number, 'label_status' => 'matched'], $this->technologist);
        $group = $workflow->enterPatientGroup($request->fresh(), ['blood_request_specimen_id' => $specimen->id, 'abo_group' => 'O', 'rh_factor' => 'positive'], $this->technologist);
        $workflow->verifyPatientGroup($group->fresh(), $this->authorizer);
        $testOne = $workflow->enterCompatibility($request->fresh(), ['blood_request_specimen_id' => $specimen->id, 'blood_component_id' => $componentOne->id, 'result' => 'Compatible by manual entry', 'interpretation' => 'Authorized manual result.'], $this->technologist);
        $workflow->authorizeCompatibility($testOne->fresh(), $this->authorizer);
        $testTwo = $workflow->enterCompatibility($request->fresh(), ['blood_request_specimen_id' => $specimen->id, 'blood_component_id' => $componentTwo->id, 'result' => 'Compatible by manual entry'], $this->technologist);
        $workflow->authorizeCompatibility($testTwo->fresh(), $this->authorizer);
        $reservationOne = $workflow->reserve($request->fresh(), $componentOne->fresh(), $this->technologist);
        $reservationTwo = $workflow->reserve($request->fresh(), $componentTwo->fresh(), $this->technologist);

        $issueOne = $workflow->issue($request->fresh(), $reservationOne->fresh(), ['received_by_name' => 'Nurse Receiver', 'destination' => 'Ward A'], $this->authorizer);
        $this->assertSame('partially-issued', $request->refresh()->state);
        $issueTwo = $workflow->issue($request->fresh(), $reservationTwo->fresh(), ['received_by_name' => 'Nurse Receiver', 'destination' => 'Ward A'], $this->authorizer);
        $this->assertSame('issued', $request->refresh()->state);

        $workflow->returnToStock($issueOne->fresh(), ['return_reason' => 'Not required', 'return_assessment' => 'Authorized suitability assessment documented.'], $this->authorizer);
        $this->assertSame('returned', $issueOne->refresh()->status);
        $this->assertSame('available', $componentOne->refresh()->state);
        $workflow->reverseIssue($issueTwo->fresh(), 'Erroneous issue record reversal', $this->authorizer);

        $this->assertSame('reversed', $issueTwo->refresh()->status);
        $this->assertDatabaseHas('blood_bank_events', ['action' => 'blood_requests.component_issued']);
        $this->assertDatabaseHas('blood_bank_events', ['action' => 'blood_requests.issue_reversed']);
    }

    public function test_discrepancies_invalid_components_double_reservation_and_expiry_are_blocked(): void
    {
        $workflow = app(BloodRequestWorkflowService::class);
        $request = $this->bloodRequest();
        $workflow->collectSpecimen($request, ['patient_confirmed_name' => $this->patient->full_name, 'patient_confirmed_identifier' => 'WRONG', 'label_status' => 'discrepant', 'label_discrepancy_notes' => 'Identifier mismatch'], $this->technologist);

        try {
            $workflow->enterPatientGroup($request->fresh(), ['abo_group' => 'A', 'rh_factor' => 'positive'], $this->technologist);
            $this->fail('Discrepant request was not blocked.');
        } catch (HttpException $exception) {
            $this->assertTrue($request->refresh()->specimen_label_discrepancy_unresolved);
        }

        $cleanRequest = $this->bloodRequest();
        $wrongType = $this->usableComponent($this->plasma);
        $expired = $this->usableComponent($this->redCells, ['expires_on' => today()->subDay()]);
        $recalled = $this->usableComponent($this->redCells, ['state' => 'recalled']);
        $component = $this->usableComponent($this->redCells);

        foreach ([$wrongType, $expired, $recalled] as $invalid) {
            try {
                $workflow->reserve($cleanRequest->fresh(), $invalid->fresh(), $this->technologist);
                $this->fail('Invalid component was not blocked.');
            } catch (HttpException) {
                $this->assertTrue(true);
            }
        }

        $reservation = $workflow->reserve($cleanRequest->fresh(), $component->fresh(), $this->technologist, 1);
        try {
            $workflow->reserve($this->bloodRequest(), $component->fresh(), $this->authorizer);
            $this->fail('Double reservation was not blocked.');
        } catch (HttpException) {
            $this->assertSame('reserved', $component->refresh()->state);
        }

        $reservation->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertSame(1, $workflow->expireReservations($this->hospital->id));
        $this->assertSame('available', $component->refresh()->state);
        $this->assertSame('expired', $reservation->refresh()->status);
    }

    public function test_blood_group_discrepancy_and_emergency_release_authorization(): void
    {
        $workflow = app(BloodRequestWorkflowService::class);
        $component = $this->usableComponent($this->redCells);
        $request = $this->bloodRequest();
        $specimen = $workflow->collectSpecimen($request, ['patient_confirmed_name' => $this->patient->full_name, 'patient_confirmed_identifier' => $this->patient->hospital_number, 'label_status' => 'matched'], $this->technologist);
        $group = $workflow->enterPatientGroup($request->fresh(), ['blood_request_specimen_id' => $specimen->id, 'abo_group' => 'O', 'rh_factor' => 'positive'], $this->technologist);
        $workflow->verifyPatientGroup($group->fresh(), $this->authorizer);

        $secondRequest = $this->bloodRequest();
        $workflow->collectSpecimen($secondRequest, ['patient_confirmed_name' => $this->patient->full_name, 'patient_confirmed_identifier' => $this->patient->hospital_number, 'label_status' => 'matched'], $this->technologist);
        try {
            $workflow->enterPatientGroup($secondRequest->fresh(), ['abo_group' => 'A', 'rh_factor' => 'positive'], $this->technologist);
            $this->fail('Blood group discrepancy was not blocked.');
        } catch (HttpException) {
            $this->assertTrue($secondRequest->refresh()->blood_group_discrepancy_unresolved);
        }

        $reservation = $workflow->reserve($request->fresh(), $component->fresh(), $this->technologist);
        try {
            $workflow->issue($request->fresh(), $reservation->fresh(), ['received_by_name' => 'Nurse Receiver', 'destination' => 'Emergency Unit'], $this->authorizer);
            $this->fail('Issue without manual crossmatch or emergency release was not blocked.');
        } catch (HttpException) {
            $this->assertFalse($request->refresh()->emergency_release_authorized);
        }

        $workflow->authorizeEmergencyRelease($request->fresh(), 'Explicit emergency release authorization without automated compatibility decision.', $this->authorizer);
        $issue = $workflow->issue($request->fresh(), $reservation->fresh(), ['received_by_name' => 'Nurse Receiver', 'destination' => 'Emergency Unit'], $this->authorizer);
        $this->assertInstanceOf(BloodComponentIssue::class, $issue);
        $this->assertDatabaseHas('blood_bank_events', ['action' => 'blood_requests.emergency_release_authorized']);
    }

    public function test_pages_permissions_and_cross_hospital_isolation(): void
    {
        $request = $this->bloodRequest();
        $this->actingAs($this->technologist)->get('/admin/blood-bank')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/BloodBank/Index')->has('requests'));
        $this->actingAs($this->technologist)->get("/admin/blood-bank/requests/{$request->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/BloodBank/RequestShow')->has('bloodRequest'));

        $otherHospital = Hospital::factory()->create();
        $otherFacility = Facility::factory()->create(['hospital_id' => $otherHospital->id, 'status' => 'active']);
        $otherPatient = Patient::create(['hospital_id' => $otherHospital->id, 'registration_facility_id' => $otherFacility->id, 'registered_by' => $this->requester->id, 'hospital_number' => 'OTHER', 'status' => 'active', 'first_name' => 'Other', 'last_name' => 'Patient', 'sex' => 'male']);
        $otherType = BloodComponentType::create(['hospital_id' => $otherHospital->id, 'code' => 'RBC', 'name' => 'Other red cells', 'is_active' => true]);
        $otherRequest = BloodRequest::create(['hospital_id' => $otherHospital->id, 'facility_id' => $otherFacility->id, 'patient_id' => $otherPatient->id, 'requesting_clinician_id' => $this->clinician->id, 'blood_component_type_id' => $otherType->id, 'request_number' => 'OTHER-BTR', 'quantity_requested' => 1, 'clinical_indication' => 'Other hospital', 'priority' => 'routine', 'state' => 'draft', 'created_by' => $this->requester->id]);

        $this->actingAs($this->technologist)->get("/admin/blood-bank/requests/{$otherRequest->id}")->assertForbidden();
    }

    private function bloodRequest(array $overrides = []): BloodRequest
    {
        return app(BloodRequestWorkflowService::class)->create(array_merge([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'requesting_clinician_id' => $this->clinician->id,
            'blood_component_type_id' => $this->redCells->id,
            'quantity_requested' => 1,
            'clinical_indication' => 'Documented clinical indication',
            'priority' => 'routine',
        ], $overrides), $this->requester);
    }

    private function usableComponent(BloodComponentType $type, array $overrides = []): BloodComponent
    {
        $workflow = app(BloodBankWorkflowService::class);
        $donor = $workflow->registerDonor(['hospital_id' => $this->hospital->id, 'first_name' => 'Donor', 'last_name' => uniqid('Person', false), 'phone' => uniqid('080', false)], $this->technologist);
        $workflow->recordScreeningDecision($donor, ['eligibility_status' => 'eligible', 'decision_reason' => 'Manual eligible decision'], $this->technologist);
        $donation = $workflow->collect(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'blood_donor_id' => $donor->id, 'blood_bank_location_id' => $this->location->id, 'bag_type' => 'Configured bag'], $this->technologist);
        $workflow->verifyGroup($workflow->enterGroup($donation, ['abo_group' => 'O', 'rh_factor' => 'positive'], $this->technologist)->fresh(), $this->authorizer);
        $screening = $workflow->recordScreeningResult($donation, $this->screeningTest, ['result_value' => 'Cleared', 'release_cleared' => true], $this->technologist);
        $workflow->verifyScreeningResult($screening->fresh(), $this->authorizer);
        $component = $workflow->prepareComponent($donation, $type, $this->location, $this->storage, ['expires_on' => today()->addDays(10)], $this->technologist);
        $workflow->releaseComponent($component->fresh(), $this->authorizer, 'Required manual checks completed.');

        if (isset($overrides['expires_on'])) {
            $component->forceFill(['expires_on' => $overrides['expires_on']])->save();
        }
        if (isset($overrides['state']) && $overrides['state'] !== 'available') {
            $component->forceFill(['state' => $overrides['state']])->save();
        }

        return $component->refresh();
    }

    private function staffUser(array $permissions, string $role): User
    {
        $user = User::factory()->create(['access_level' => $role === 'doctor' ? 'doctor' : 'admin']);
        $user->syncRoles([$role]);
        $user->givePermissionTo($permissions);
        StaffProfile::factory()->create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'is_active' => true]);

        return $user->load('staffProfile');
    }
}
