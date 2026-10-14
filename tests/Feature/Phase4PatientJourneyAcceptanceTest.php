<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\QueueEntry;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Services\InvoiceWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4PatientJourneyAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_appointment_encounter_and_billing_retain_one_patient_identity(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $facility = Facility::factory()->create(['hospital_id' => $hospital->id, 'status' => 'active', 'is_primary' => true]);
        $department = Department::factory()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $hospital->id, 'default_facility_id' => $facility->id]);
        NumberSequence::create(['hospital_id' => $hospital->id, 'key' => 'patient_number', 'label' => 'Patient', 'prefix' => 'PAT', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);

        $receptionist = User::factory()->create(['access_level' => 'admin']);
        $receptionist->syncRoles(['receptionist']);
        $receptionist->givePermissionTo(['patients.view', 'patients.register']);
        StaffProfile::factory()->create(['user_id' => $receptionist->id, 'hospital_id' => $hospital->id, 'staff_category' => 'administrative']);

        $doctor = User::factory()->create(['access_level' => 'admin']);
        $doctor->syncRoles(['doctor']);
        $doctor->givePermissionTo(['encounters.view', 'encounters.manage', 'encounters.sign']);
        $clinician = StaffProfile::factory()->create(['user_id' => $doctor->id, 'hospital_id' => $hospital->id, 'staff_category' => 'clinical']);

        $this->actingAs($receptionist)->post('/admin/patients', [
            'registration_facility_id' => $facility->id, 'first_name' => 'Journey',
            'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female',
            'is_dob_estimated' => false, 'identifiers' => [], 'contacts' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $patient = Patient::where('hospital_id', $hospital->id)->sole();
        $this->assertSame('PAT-0001', $patient->hospital_number);
        $type = AppointmentType::create(['hospital_id' => $hospital->id, 'name' => 'Consultation', 'code' => 'CONSULT', 'duration_minutes' => 30, 'is_active' => true]);
        $appointment = Appointment::create([
            'hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id,
            'patient_id' => $patient->id, 'clinician_id' => $clinician->id, 'appointment_type_id' => $type->id,
            'starts_at' => now(), 'ends_at' => now()->addMinutes(30), 'status' => 'checked_in',
            'source' => 'staff', 'booked_by' => $receptionist->id,
        ]);
        $visit = Visit::create([
            'hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id,
            'patient_id' => $patient->id, 'clinician_id' => $clinician->id, 'appointment_id' => $appointment->id,
            'source' => 'appointment', 'status' => 'checked_in', 'checked_in_by' => $receptionist->id,
            'checked_in_at' => now(),
        ]);
        $queue = QueueEntry::create([
            'hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id,
            'patient_id' => $patient->id, 'visit_id' => $visit->id, 'clinician_id' => $clinician->id,
            'queue_date' => today()->toDateString(), 'queue_number' => 1, 'priority' => 3,
            'status' => 'called', 'created_by' => $receptionist->id,
        ]);

        $this->actingAs($doctor)->post("/admin/visits/{$visit->id}/encounter")->assertRedirect();
        $encounter = ClinicalEncounter::where('visit_id', $visit->id)->sole();
        $this->assertSame($patient->id, $encounter->patient_id);
        $this->assertSame('in_encounter', $queue->refresh()->status);
        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/assessment", [
            'presenting_complaint' => 'Routine consultation', 'treatment_plan' => 'Review and follow up',
        ])->assertRedirect();
        $this->actingAs($doctor)->patch("/admin/encounters/{$encounter->id}/transition", ['action' => 'sign'])->assertRedirect();
        $this->assertSame('signed', $encounter->refresh()->status);
        $this->assertSame('completed', $visit->refresh()->status);
        $this->assertSame('removed', $queue->refresh()->status);

        $invoice = app(InvoiceWorkflowService::class)->createDraft([
            'facility_id' => $facility->id, 'visit_id' => $visit->id,
            'clinical_encounter_id' => $encounter->id, 'currency' => 'NGN',
        ], $patient, $receptionist);
        $this->assertSame($patient->id, $invoice->patient_id);
        $this->assertSame($visit->id, $invoice->visit_id);
        $this->assertSame($encounter->id, $invoice->clinical_encounter_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'patients.registered', 'subject_id' => $patient->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'encounters.sign', 'subject_id' => $encounter->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'invoices.created', 'subject_id' => $invoice->id]);
    }
}
