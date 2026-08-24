<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\BedClass;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InpatientAmendment;
use App\Models\InpatientChart;
use App\Models\InpatientChartEvent;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionWorkflowService;
use App\Services\InpatientChartWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase6BInpatientClinicalChartTest extends TestCase
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

    private Admission $admission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->hospital = Hospital::create(['legal_name' => 'Phase 6B Hospital', 'display_name' => 'Phase 6B', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
        $this->facility = Facility::create(['hospital_id' => $this->hospital->id, 'code' => 'P6B', 'name' => 'Phase 6B Facility', 'facility_type' => 'hospital', 'status' => 'active', 'is_primary' => true]);
        $this->department = Department::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'MED', 'name' => 'Medicine', 'category' => 'clinical', 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => 'admission_number', 'label' => 'admission', 'prefix' => 'ADM', 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        $this->doctor = $this->user('doctor', ['admissions.view', 'admissions.request', 'admissions.approve', 'admissions.manage', 'inpatient.view', 'inpatient.document', 'inpatient.sign', 'inpatient.orders', 'inpatient.handover', 'inpatient.discharge-summary.sign']);
        $this->nurse = $this->user('nurse', ['inpatient.view', 'inpatient.document', 'inpatient.orders', 'inpatient.handover']);
        $this->patient = Patient::create(['hospital_id' => $this->hospital->id, 'registration_facility_id' => $this->facility->id, 'registered_by' => $this->doctor->id, 'hospital_number' => 'P6B-001', 'status' => 'active', 'first_name' => 'Inpatient', 'last_name' => 'Chart', 'sex' => 'female']);
        $this->visit = Visit::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'patient_id' => $this->patient->id, 'clinician_id' => $this->doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $this->doctor->id, 'checked_in_at' => now()]);
        $this->encounter = ClinicalEncounter::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'patient_id' => $this->patient->id, 'visit_id' => $this->visit->id, 'responsible_clinician_id' => $this->doctor->staffProfile->id, 'source' => 'outpatient', 'status' => 'in_progress', 'started_by' => $this->doctor->id, 'started_at' => now()]);
        $this->admission = $this->activeAdmission();
    }

    public function test_active_admission_chart_progress_note_sign_and_amendment(): void
    {
        $workflow = app(InpatientChartWorkflowService::class);
        $chart = $workflow->chartForAdmission($this->admission, $this->doctor);
        $note = $workflow->progressNote($chart, ['subjective' => 'Patient reports symptoms', 'objective' => 'Clinician observations', 'assessment' => 'Working inpatient assessment', 'plan' => 'Continue monitoring'], $this->doctor);

        $workflow->signProgressNote($note, $this->doctor);
        $this->assertSame('signed', $note->refresh()->status);

        $workflow->amend($note->fresh(), ['reason' => 'Clarification', 'content' => 'Clarified entered assessment'], $this->doctor);
        $this->assertTrue(InpatientAmendment::where('amendable_id', $note->id)->exists());
        $this->assertDatabaseHas('patient_activity_events', ['patient_id' => $this->patient->id, 'action' => 'inpatient.progress_note_created']);

        $this->admission->forceFill(['status' => 'discharged'])->save();
        $this->expectException(HttpException::class);
        $workflow->chartForAdmission($this->admission->fresh(), $this->doctor);
    }

    public function test_observations_intake_output_care_plan_order_handover_and_discharge_summary(): void
    {
        $workflow = app(InpatientChartWorkflowService::class);
        $chart = $workflow->chartForAdmission($this->admission, $this->doctor);
        $workflow->observation($chart, ['temperature_unit' => 'C', 'temperature' => 37.2, 'pulse' => 88, 'respiratory_rate' => 18, 'blood_pressure_systolic' => 120, 'blood_pressure_diastolic' => 80, 'oxygen_saturation' => 98, 'pain_score' => 2, 'glucose' => 5.8, 'glucose_unit' => 'mmol/L', 'consciousness_notes' => 'Awake', 'observed_at' => now()], $this->nurse);
        $workflow->intakeOutput($chart, ['direction' => 'intake', 'measurement_type' => 'oral fluids', 'quantity' => 250, 'unit' => 'ml', 'measured_at' => now()], $this->nurse);
        $workflow->carePlan($chart, ['problem' => 'Mobility support', 'goal' => 'Safe mobilisation', 'intervention' => 'Assist with ambulation', 'evaluation' => 'Ongoing', 'status' => 'active'], $this->nurse);
        $workflow->diagnosis($chart, ['description' => 'Configured inpatient problem', 'status' => 'provisional'], $this->doctor);
        $order = $workflow->order($chart, ['order_type' => 'monitoring', 'instruction' => 'Record observations as clinically requested', 'status' => 'active'], $this->doctor);
        $workflow->transitionOrder($order, 'acknowledge', $this->nurse);
        $workflow->transitionOrder($order->fresh(), 'complete', $this->nurse);
        $handover = $workflow->handover($chart, ['from_shift' => 'day', 'to_shift' => 'night', 'summary' => 'Stable during shift'], $this->nurse);
        $workflow->acknowledgeHandover($handover, $this->doctor);
        $summary = $workflow->dischargeSummary($chart, ['clinical_course' => 'Reviewed and stable', 'discharge_plan' => 'Clinician reviewed plan'], $this->doctor);
        $workflow->signDischargeSummary($summary, $this->doctor);

        $this->assertSame('closed', $chart->refresh()->status);
        $this->assertDatabaseHas('inpatient_observations', ['inpatient_chart_id' => $chart->id, 'pulse' => 88]);
        $this->assertDatabaseHas('inpatient_intake_outputs', ['inpatient_chart_id' => $chart->id, 'unit' => 'ml']);
        $this->assertDatabaseHas('inpatient_care_plans', ['inpatient_chart_id' => $chart->id, 'status' => 'active']);
        $this->assertSame('completed', $order->refresh()->status);
        $this->assertSame('acknowledged', $handover->refresh()->status);
        $this->assertSame('signed', $summary->refresh()->status);
        $this->assertTrue(InpatientChartEvent::where('action', 'inpatient.discharge_summary_signed')->exists());
    }

    public function test_authorization_cross_hospital_isolation_and_pages(): void
    {
        $workflow = app(InpatientChartWorkflowService::class);
        $chart = $workflow->chartForAdmission($this->admission, $this->doctor);
        $unauthorized = $this->user('cashier', ['patients.view']);

        $this->actingAs($unauthorized)->get('/admin/inpatient')->assertForbidden();
        $this->actingAs($this->doctor)->get('/admin/inpatient')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inpatient/Index')->has('admissions')->has('tasks'));
        $this->actingAs($this->doctor)->get("/admin/inpatient/charts/{$chart->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inpatient/Chart')->has('chart')->has('timeline'));

        $otherHospital = Hospital::create(['legal_name' => 'Other 6B', 'display_name' => 'Other 6B', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos']);
        $otherChart = InpatientChart::create(['hospital_id' => $otherHospital->id, 'facility_id' => $this->facility->id, 'admission_id' => Admission::create(['hospital_id' => $otherHospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'status' => 'admitted', 'requested_by' => $this->doctor->id])->id, 'patient_id' => $this->patient->id, 'status' => 'active', 'opened_by' => $this->doctor->id, 'opened_at' => now()]);
        $this->assertFalse($this->doctor->can('view', $otherChart));
    }

    private function activeAdmission(): Admission
    {
        $ward = Ward::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'code' => 'WARD', 'name' => 'Ward', 'status' => 'active']);
        $room = WardRoom::create(['hospital_id' => $this->hospital->id, 'ward_id' => $ward->id, 'code' => 'R1', 'name' => 'Room', 'status' => 'active']);
        $class = BedClass::create(['hospital_id' => $this->hospital->id, 'code' => 'GEN', 'name' => 'General', 'is_active' => true]);
        $bed = Bed::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $class->id, 'code' => 'B1', 'label' => 'Bed 1', 'state' => 'available']);
        $admissionWorkflow = app(AdmissionWorkflowService::class);
        $admission = $admissionWorkflow->request(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'patient_id' => $this->patient->id, 'visit_id' => $this->visit->id, 'clinical_encounter_id' => $this->encounter->id, 'department_id' => $this->department->id, 'reason' => 'Requires inpatient documentation', 'provisional_diagnosis' => 'Configured inpatient problem'], $this->doctor);
        $admissionWorkflow->approve($admission, $this->doctor);

        return $admissionWorkflow->admit($admission->fresh(), $bed, $this->doctor);
    }

    private function user(string $role, array $permissions): User
    {
        $user = User::factory()->create(['access_level' => $role, 'status' => 'active']);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);
        StaffProfile::create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'staff_number' => strtoupper($role).'-'.substr(md5((string) $user->id), 0, 6), 'job_title' => $role, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);

        return $user->load('staffProfile', 'roles');
    }
}
