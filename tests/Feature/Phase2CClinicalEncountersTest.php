<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\PatientAllergy;
use App\Models\QueueEntry;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase2CClinicalEncountersTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private Department $department;

    private StaffProfile $clinician;

    private Patient $patient;

    private Visit $visit;

    private QueueEntry $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['timezone' => 'Africa/Lagos']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        $this->department = Department::factory()->create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'category' => 'clinical', 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id]);
        $this->clinician = $this->staffUser(['encounters.view', 'encounters.manage', 'encounters.sign', 'vitals.record'], 'doctor')->staffProfile;
        $this->patient = $this->patient();
        $this->visit = $this->visit();
        $this->queue = $this->queue($this->visit);
    }

    public function test_encounter_lifecycle_vitals_diagnosis_sign_and_amendment(): void
    {
        $doctor = $this->clinician->user;

        $this->actingAs($doctor)->post("/admin/visits/{$this->visit->id}/encounter")->assertRedirect();
        $encounter = ClinicalEncounter::firstOrFail();
        $this->assertSame('in_encounter', $this->visit->refresh()->status);
        $this->assertSame('in_encounter', $this->queue->refresh()->status);

        $this->actingAs($doctor)->post("/admin/encounters/{$encounter->id}/vitals", $this->vitalsPayload())->assertRedirect();
        $this->assertDatabaseHas('encounter_vitals', ['clinical_encounter_id' => $encounter->id, 'bmi' => 24.22]);

        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/assessment", [
            'presenting_complaint' => 'Cough',
            'history_presenting_complaint' => 'Two days',
            'medical_history' => 'No chronic illness recorded',
            'examination_findings' => 'Chest clear',
            'treatment_plan' => 'Supportive management discussed',
            'follow_up_instructions' => 'Return if symptoms worsen',
            'follow_up_date' => now()->addWeek()->toDateString(),
            'referral_recommendation' => 'No referral recommended today',
        ])->assertRedirect();
        $this->actingAs($doctor)->post("/admin/encounters/{$encounter->id}/diagnoses", [
            'description' => 'Upper respiratory symptoms',
            'coding_system' => 'LOCAL',
            'code' => 'URS',
            'status' => 'provisional',
        ])->assertRedirect();

        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/transition", ['action' => 'pause'])->assertRedirect();
        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/transition", ['action' => 'resume'])->assertRedirect();
        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/transition", ['action' => 'sign'])->assertRedirect();

        $this->assertSame('signed', $encounter->refresh()->status);
        $this->assertSame('completed', $this->visit->refresh()->status);
        $this->assertSame('removed', $this->queue->refresh()->status);
        $this->assertDatabaseHas('clinical_encounter_events', ['clinical_encounter_id' => $encounter->id, 'action' => 'sign']);
        $this->assertDatabaseHas('audit_events', ['action' => 'encounters.sign']);

        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/assessment", ['presenting_complaint' => 'Changed'])->assertStatus(422);
        $this->actingAs($doctor)->post("/admin/encounters/{$encounter->id}/amendments", ['reason' => 'Clarification', 'content' => 'Patient later clarified symptom onset.'])->assertRedirect();
        $this->assertDatabaseHas('encounter_amendments', ['clinical_encounter_id' => $encounter->id, 'reason' => 'Clarification']);
    }

    public function test_authorization_vitals_signing_and_idor_are_enforced(): void
    {
        $nurse = $this->staffUser(['encounters.view', 'vitals.record'], 'nurse');
        $doctor = $this->clinician->user;
        $this->actingAs($doctor)->post("/admin/visits/{$this->visit->id}/encounter")->assertRedirect();
        $encounter = ClinicalEncounter::firstOrFail();

        $this->actingAs($nurse)->post("/admin/encounters/{$encounter->id}/vitals", $this->vitalsPayload())->assertRedirect();
        $this->actingAs($nurse)->patch("/admin/encounters/{$encounter->id}/transition", ['action' => 'sign'])->assertForbidden();
        $this->actingAs($nurse)->post("/admin/encounters/{$encounter->id}/diagnoses", ['description' => 'Not allowed', 'status' => 'provisional'])->assertForbidden();

        $otherHospital = Hospital::factory()->create();
        $otherFacility = Facility::factory()->create(['hospital_id' => $otherHospital->id]);
        $otherPatient = Patient::create($this->patientPayload(['hospital_id' => $otherHospital->id, 'registration_facility_id' => $otherFacility->id, 'hospital_number' => 'OTHER']));
        $otherVisit = Visit::create($this->visitPayload(['hospital_id' => $otherHospital->id, 'facility_id' => $otherFacility->id, 'patient_id' => $otherPatient->id]));
        $otherEncounter = ClinicalEncounter::create($this->encounterPayload($otherVisit));

        $this->actingAs($doctor)->get("/admin/encounters/{$otherEncounter->id}")->assertForbidden();
    }

    public function test_multiple_active_encounters_for_same_visit_are_rejected(): void
    {
        $doctor = $this->clinician->user;
        $this->actingAs($doctor)->post("/admin/visits/{$this->visit->id}/encounter")->assertRedirect();
        $this->actingAs($doctor)->post("/admin/visits/{$this->visit->id}/encounter")->assertStatus(422);
        $this->assertSame(1, ClinicalEncounter::count());
    }

    public function test_cancelled_encounter_returns_visit_to_queue(): void
    {
        $doctor = $this->clinician->user;
        $this->actingAs($doctor)->post("/admin/visits/{$this->visit->id}/encounter")->assertRedirect();
        $encounter = ClinicalEncounter::firstOrFail();

        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/transition", ['action' => 'cancel', 'reason' => 'Opened in error'])->assertRedirect();

        $this->assertSame('cancelled', $encounter->refresh()->status);
        $this->assertSame('checked_in', $this->visit->refresh()->status);
        $this->assertSame('waiting', $this->queue->refresh()->status);
    }

    public function test_worklist_and_encounter_pages_render_timeline_alerts_and_allergies(): void
    {
        $doctor = $this->clinician->user;
        PatientAllergy::create(['patient_id' => $this->patient->id, 'hospital_id' => $this->hospital->id, 'substance' => 'Penicillin', 'severity' => 'severe', 'status' => 'active', 'recorded_by' => $doctor->id, 'recorded_at' => now()]);
        PatientAlert::create(['patient_id' => $this->patient->id, 'hospital_id' => $this->hospital->id, 'title' => 'Fall risk', 'category' => 'safety', 'severity' => 'high', 'status' => 'active', 'recorded_by' => $doctor->id, 'recorded_at' => now()]);
        $this->actingAs($doctor)->post("/admin/visits/{$this->visit->id}/encounter")->assertRedirect();
        $encounter = ClinicalEncounter::firstOrFail();
        $this->actingAs($doctor)->post("/admin/encounters/{$encounter->id}/vitals", $this->vitalsPayload())->assertRedirect();

        $this->actingAs($doctor)->get('/admin/clinical/worklist')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Clinical/Worklist')->has('visits')->has('encounters'));

        $this->actingAs($doctor)->get("/admin/encounters/{$encounter->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Clinical/Encounter')
                ->has('encounter.patient.allergies', 1)
                ->has('encounter.patient.alerts', 1)
                ->has('timeline'));
    }

    private function vitalsPayload(array $overrides = []): array
    {
        return array_merge([
            'temperature' => '37.2',
            'temperature_unit' => 'C',
            'pulse' => 80,
            'respiratory_rate' => 18,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 78,
            'oxygen_saturation' => 98,
            'weight_kg' => 70,
            'height_cm' => 170,
            'pain_score' => 2,
            'measured_at' => now()->toDateTimeString(),
            'notes' => 'Recorded at triage',
        ], $overrides);
    }

    private function encounterPayload(Visit $visit): array
    {
        return [
            'hospital_id' => $visit->hospital_id,
            'facility_id' => $visit->facility_id,
            'department_id' => $visit->department_id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'responsible_clinician_id' => $this->clinician->id,
            'status' => 'in_progress',
            'source' => 'walk_in',
            'started_by' => $this->clinician->user_id,
            'started_at' => now(),
        ];
    }

    private function visit(): Visit
    {
        $type = AppointmentType::create(['hospital_id' => $this->hospital->id, 'name' => 'Consultation', 'code' => 'CONSULT', 'duration_minutes' => 30, 'is_active' => true]);
        $appointment = Appointment::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'department_id' => $this->department->id,
            'patient_id' => $this->patient->id,
            'clinician_id' => $this->clinician->id,
            'appointment_type_id' => $type->id,
            'starts_at' => now(),
            'ends_at' => now()->addMinutes(30),
            'status' => 'checked_in',
            'source' => 'staff',
            'booked_by' => $this->clinician->user_id,
        ]);

        return Visit::create($this->visitPayload(['appointment_id' => $appointment->id]));
    }

    private function visitPayload(array $overrides = []): array
    {
        return array_merge([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'department_id' => $this->department->id,
            'patient_id' => $this->patient->id,
            'clinician_id' => $this->clinician->id,
            'source' => 'appointment',
            'status' => 'checked_in',
            'checked_in_by' => $this->clinician->user_id,
            'checked_in_at' => now(),
        ], $overrides);
    }

    private function queue(Visit $visit): QueueEntry
    {
        return QueueEntry::create([
            'hospital_id' => $visit->hospital_id,
            'facility_id' => $visit->facility_id,
            'department_id' => $visit->department_id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'clinician_id' => $visit->clinician_id,
            'queue_date' => now()->toDateString(),
            'queue_number' => 1,
            'priority' => 3,
            'status' => 'called',
            'created_by' => $this->clinician->user_id,
        ]);
    }

    private function patient(string $number = 'PAT-2C'): Patient
    {
        $patient = Patient::create($this->patientPayload(['hospital_number' => $number]));
        $patient->phone = '08030000000';
        $patient->save();

        return $patient;
    }

    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->clinician?->user_id ?? User::factory()->create()->id,
            'hospital_number' => 'PAT-2C',
            'first_name' => 'Ada',
            'last_name' => 'Clinical',
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
        StaffProfile::factory()->create([
            'user_id' => $user->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'clinical',
            'is_active' => true,
        ]);

        return $user->load('staffProfile');
    }
}
