<?php

namespace Tests\Feature;

use App\Models\BloodBankLocation;
use App\Models\BloodComponent;
use App\Models\BloodComponentIssue;
use App\Models\BloodComponentType;
use App\Models\BloodDonation;
use App\Models\BloodDonor;
use App\Models\BloodRequest;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BloodTransfusionWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase7CTransfusionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private Facility $facility;
    private User $nurse;
    private StaffProfile $clinician;
    private Patient $patient;
    private BloodBankLocation $location;
    private BloodComponentType $componentType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create();
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);

        $this->nurse = User::factory()->create(['access_level' => 'nurse']);
        $this->nurse->syncRoles(['nurse']);
        $this->clinician = StaffProfile::factory()->create([
            'user_id' => $this->nurse->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'nurse',
            'is_active' => true,
            'employment_status' => 'active',
        ]);

        $this->patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->nurse->id,
            'hospital_number' => 'PAT-TRANS-001',
            'first_name' => 'Transfusion',
            'last_name' => 'Patient',
            'sex' => 'female',
            'status' => 'active',
        ]);

        $this->location = BloodBankLocation::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'code' => 'BB',
            'name' => 'Blood Bank',
            'is_active' => true,
        ]);

        $this->componentType = BloodComponentType::create([
            'hospital_id' => $this->hospital->id,
            'code' => 'RBC',
            'name' => 'Red cells',
            'is_active' => true,
        ]);
    }

    public function test_exact_bedside_identity_is_required_before_transfusion_can_start(): void
    {
        $issue = $this->issue('001');
        $workflow = app(BloodTransfusionWorkflowService::class);

        try {
            $workflow->start($issue, [
                'patient_identifier_checked' => 'WRONG-PATIENT',
                'component_identifier_checked' => $issue->component->component_number,
                'identity_check_status' => 'matched',
            ], $this->nurse);
            $this->fail('Mismatched patient identity was not blocked.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $episode = $workflow->start($issue->fresh(), [
            'patient_identifier_checked' => $this->patient->hospital_number,
            'component_identifier_checked' => $issue->component->component_number,
            'identity_check_status' => 'matched',
            'destination' => 'Ward A',
        ], $this->nurse);

        $this->assertSame('in_progress', $episode->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'blood_transfusion.started']);
    }

    public function test_observation_reaction_stop_and_resolution_preserve_audit_history(): void
    {
        $issue = $this->issue('002');
        $workflow = app(BloodTransfusionWorkflowService::class);
        $episode = $workflow->start($issue, [
            'patient_identifier_checked' => $this->patient->hospital_number,
            'component_identifier_checked' => $issue->component->component_number,
            'identity_check_status' => 'matched',
        ], $this->nurse);

        $workflow->observe($episode->fresh(), [
            'temperature_c' => 37.2,
            'pulse_bpm' => 88,
            'respiratory_rate' => 18,
            'systolic_bp' => 118,
            'diastolic_bp' => 76,
            'spo2_percent' => 98,
            'notes' => 'Observed and documented by authorized staff.',
        ], $this->nurse);

        $reaction = $workflow->reportReaction($episode->fresh(), [
            'reported_severity' => 'unspecified',
            'observed_signs' => 'Observed signs documented without automated diagnosis.',
            'immediate_actions' => 'Actions documented by authorized staff.',
        ], $this->nurse);

        $this->assertSame('stopped', $episode->refresh()->status);
        $this->assertSame('open', $reaction->status);

        $workflow->resolveReaction($reaction->fresh(), 'Follow-up documented and record closed.', $this->nurse);

        $this->assertDatabaseHas('blood_transfusion_observations', ['blood_transfusion_episode_id' => $episode->id]);
        $this->assertDatabaseHas('blood_transfusion_reactions', ['id' => $reaction->id, 'status' => 'resolved']);
        $this->assertDatabaseHas('audit_events', ['action' => 'blood_transfusion.reaction_reported']);
        $this->assertDatabaseHas('audit_events', ['action' => 'blood_transfusion.reaction_resolved']);
    }

    public function test_transfusion_can_be_completed_and_page_is_hospital_scoped(): void
    {
        $issue = $this->issue('003');
        $workflow = app(BloodTransfusionWorkflowService::class);
        $episode = $workflow->start($issue, [
            'patient_identifier_checked' => $this->patient->hospital_number,
            'component_identifier_checked' => $issue->component->component_number,
            'identity_check_status' => 'matched',
        ], $this->nurse);
        $workflow->complete($episode->fresh(), ['notes' => 'Completion documented.'], $this->nurse);

        $this->assertSame('completed', $episode->refresh()->status);
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->actingAs($this->nurse)->get("/admin/blood-bank/issues/{$issue->id}/transfusion")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/BloodBank/TransfusionShow')->has('issue.transfusion_episode'));
    }

    private function issue(string $suffix): BloodComponentIssue
    {
        $donor = BloodDonor::create([
            'hospital_id' => $this->hospital->id,
            'registered_by' => $this->nurse->id,
            'donor_number' => "DONOR-{$suffix}",
            'first_name' => 'Donor',
            'last_name' => $suffix,
            'status' => 'active',
        ]);

        $donation = BloodDonation::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'blood_donor_id' => $donor->id,
            'blood_bank_location_id' => $this->location->id,
            'donation_number' => "DON-{$suffix}",
            'collection_number' => "BAG-{$suffix}",
            'collected_at' => now(),
            'collected_by' => $this->nurse->id,
            'bag_type' => 'Configured bag',
            'status' => 'collected',
        ]);

        $component = BloodComponent::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'blood_donation_id' => $donation->id,
            'blood_component_type_id' => $this->componentType->id,
            'blood_bank_location_id' => $this->location->id,
            'component_number' => "BCP-{$suffix}",
            'state' => 'issued',
            'prepared_by' => $this->nurse->id,
            'prepared_at' => now(),
        ]);

        $request = BloodRequest::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $this->patient->id,
            'requesting_clinician_id' => $this->clinician->id,
            'blood_component_type_id' => $this->componentType->id,
            'request_number' => "BTR-{$suffix}",
            'quantity_requested' => 1,
            'quantity_issued' => 1,
            'clinical_indication' => 'Documented clinical indication',
            'priority' => 'routine',
            'state' => 'issued',
            'created_by' => $this->nurse->id,
        ]);

        return BloodComponentIssue::create([
            'hospital_id' => $this->hospital->id,
            'blood_request_id' => $request->id,
            'blood_component_id' => $component->id,
            'issue_number' => "BIS-{$suffix}",
            'patient_id' => $this->patient->id,
            'issued_by' => $this->nurse->id,
            'received_by_name' => 'Ward Nurse',
            'receiver_role' => 'nurse',
            'issued_at' => now(),
            'destination' => 'Ward A',
            'status' => 'issued',
        ])->load(['request.patient', 'component']);
    }
}
