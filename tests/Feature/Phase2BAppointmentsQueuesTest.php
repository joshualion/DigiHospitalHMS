<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ClinicianSchedule;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\PublicAppointmentRequest;
use App\Models\QueueEntry;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use App\Services\SensitiveLookup;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase2BAppointmentsQueuesTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private Department $department;

    private StaffProfile $clinician;

    private AppointmentType $type;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['timezone' => 'Africa/Lagos']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        $this->department = Department::factory()->create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'category' => 'clinical', 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id]);
        NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => 'patient_number', 'label' => 'Patient', 'prefix' => 'PAT', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        $this->type = AppointmentType::create(['hospital_id' => $this->hospital->id, 'name' => 'Consultation', 'code' => 'CONSULT', 'duration_minutes' => 30, 'is_active' => true]);
        $this->clinician = $this->staffUser(['appointments.view', 'appointments.manage', 'queues.view', 'queues.manage'], 'doctor')->staffProfile;
        ClinicianSchedule::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id, 'staff_profile_id' => $this->clinician->id, 'day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '11:00', 'breaks' => [['starts_at' => '10:00', 'ends_at' => '10:30']], 'is_active' => true]);
        $this->patient = $this->patient();
    }

    public function test_availability_uses_schedule_breaks_and_existing_bookings(): void
    {
        $date = '2026-08-24';
        Appointment::create($this->appointmentPayload(['starts_at' => "{$date} 09:00:00", 'ends_at' => "{$date} 09:30:00"]));

        $slots = app(AppointmentAvailabilityService::class)->slots($this->hospital, $this->clinician, $this->type, $date, $this->facility->id);

        $this->assertSame(["{$date}T09:30:00+01:00", "{$date}T10:30:00+01:00"], array_column($slots, 'starts_at'));
    }

    public function test_booking_conflicts_reschedule_cancel_confirm_no_show_and_history(): void
    {
        $user = $this->staffUser(['appointments.view', 'appointments.book', 'appointments.manage'], 'receptionist');

        $this->actingAs($user)->post('/admin/appointments', $this->bookingPayload(['starts_at' => '2026-08-24 09:00:00']))->assertRedirect();
        $appointment = Appointment::firstOrFail();

        $this->actingAs($user)->post('/admin/appointments', $this->bookingPayload(['starts_at' => '2026-08-24 09:15:00']))
            ->assertStatus(422);

        $this->actingAs($user)->patch("/admin/appointments/{$appointment->id}/transition", ['action' => 'confirm'])->assertRedirect();
        $this->actingAs($user)->patch("/admin/appointments/{$appointment->id}/transition", ['action' => 'reschedule', 'starts_at' => '2026-08-24 09:30:00', 'reason' => 'Patient requested'])->assertRedirect();
        $this->actingAs($user)->patch("/admin/appointments/{$appointment->id}/transition", ['action' => 'cancel', 'reason' => 'Patient unavailable'])->assertRedirect();
        $this->actingAs($user)->patch("/admin/appointments/{$appointment->id}/transition", ['action' => 'no_show', 'reason' => 'Did not arrive'])->assertRedirect();

        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'action' => 'booked']);
        $this->assertDatabaseHas('appointment_events', ['appointment_id' => $appointment->id, 'action' => 'reschedule']);
        $this->assertDatabaseHas('audit_events', ['action' => 'appointments.cancel']);
        $this->assertSame('no_show', $appointment->refresh()->status);
    }

    public function test_public_request_is_encrypted_reviewed_and_does_not_create_patient_or_appointment(): void
    {
        $this->post('/appointment/request', [
            'name' => 'Public Person',
            'phone' => '08033334444',
            'preferred_facility_id' => $this->facility->id,
            'preferred_department_id' => $this->department->id,
            'preferred_date' => '2026-08-24',
            'consent' => '1',
            'website' => '',
        ])->assertRedirect();

        $request = PublicAppointmentRequest::firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame('08033334444', $request->phone);
        $this->assertNotSame('08033334444', DB::table('public_appointment_requests')->whereKey($request->id)->value('phone_encrypted'));
        $this->assertSame(app(SensitiveLookup::class)->hash('08033334444'), $request->phone_hash);
        $this->assertSame(1, PublicAppointmentRequest::count());
        $this->assertSame(1, Patient::count());
        $this->assertSame(0, Appointment::count());

        $reviewer = $this->staffUser(['appointment-requests.review'], 'receptionist');
        $this->actingAs($reviewer)->patch("/admin/appointment-requests/{$request->id}", ['status' => 'declined', 'reason' => 'No availability'])->assertRedirect();
        $this->assertDatabaseHas('audit_events', ['action' => 'appointment_requests.declined']);
    }

    public function test_check_in_walk_ins_queue_actions_priority_authorization_and_audits(): void
    {
        $user = $this->staffUser(['appointments.view', 'appointments.book', 'appointments.manage', 'queues.view', 'queues.manage', 'queues.prioritize'], 'receptionist');
        $this->actingAs($user)->post('/admin/appointments', $this->bookingPayload(['starts_at' => '2026-08-24 09:00:00']))->assertRedirect();
        $appointment = Appointment::firstOrFail();

        $this->actingAs($user)->post("/admin/appointments/{$appointment->id}/check-in")->assertRedirect('/admin/queues');
        $queue = QueueEntry::firstOrFail();
        $this->assertSame(1, $queue->queue_number);
        $this->assertDatabaseHas('visits', ['appointment_id' => $appointment->id, 'source' => 'appointment']);

        foreach (['call', 'recall', 'skip'] as $action) {
            $this->actingAs($user)->patch("/admin/queues/{$queue->id}", ['action' => $action, 'reason' => $action === 'skip' ? 'Temporarily unavailable' : null])->assertRedirect();
        }
        $this->actingAs($user)->patch("/admin/queues/{$queue->id}", ['action' => 'priority', 'priority' => 1, 'reason' => 'Mobility support'])->assertRedirect();

        $walkInPatient = $this->patient('PAT-1000');
        $this->actingAs($user)->post('/admin/appointments/walk-ins', ['patient_id' => $walkInPatient->id, 'facility_id' => $this->facility->id, 'department_id' => $this->department->id])->assertRedirect('/admin/queues');

        $this->assertDatabaseHas('queue_events', ['queue_entry_id' => $queue->id, 'action' => 'priority']);
        $this->assertDatabaseHas('audit_events', ['action' => 'queues.priority']);
        $this->assertSame(2, QueueEntry::count());
    }

    public function test_archived_patients_authorization_and_idor_are_rejected(): void
    {
        $viewer = $this->staffUser(['appointments.view', 'queues.view'], 'nurse');
        $archived = $this->patient('PAT-ARCH', 'archived');

        $this->actingAs($viewer)->post('/admin/appointments', $this->bookingPayload(['patient_id' => $archived->id]))->assertForbidden();

        $booker = $this->staffUser(['appointments.view', 'appointments.book'], 'receptionist');
        $this->actingAs($booker)->post('/admin/appointments', $this->bookingPayload(['patient_id' => $archived->id]))->assertStatus(422);

        $otherHospital = Hospital::factory()->create();
        $otherFacility = Facility::factory()->create(['hospital_id' => $otherHospital->id]);
        $other = Appointment::create($this->appointmentPayload(['hospital_id' => $otherHospital->id, 'facility_id' => $otherFacility->id]));
        $this->actingAs($booker)->patch("/admin/appointments/{$other->id}/transition", ['action' => 'confirm'])->assertForbidden();
    }

    public function test_admin_pages_render_with_inertia(): void
    {
        $user = $this->staffUser(['appointments.view', 'queues.view'], 'receptionist');

        $this->actingAs($user)->get('/admin/appointments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Appointments/Index')->has('appointments')->has('requests'));

        $this->actingAs($user)->get('/admin/queues')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Appointments/Queue')->has('queue'));
    }

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $this->patient->id,
            'facility_id' => $this->facility->id,
            'department_id' => $this->department->id,
            'clinician_id' => $this->clinician->id,
            'appointment_type_id' => $this->type->id,
            'starts_at' => '2026-08-24 09:00:00',
            'reason' => 'Routine visit',
        ], $overrides);
    }

    private function appointmentPayload(array $overrides = []): array
    {
        return array_merge($this->bookingPayload(), [
            'hospital_id' => $this->hospital->id,
            'ends_at' => '2026-08-24 09:30:00',
            'status' => 'scheduled',
            'source' => 'staff',
            'booked_by' => $this->clinician->user_id,
        ], $overrides);
    }

    private function patient(string $number = 'PAT-0001', string $status = 'active'): Patient
    {
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->clinician?->user_id ?? User::factory()->create()->id,
            'hospital_number' => $number,
            'first_name' => 'Ada',
            'last_name' => 'Queue',
            'date_of_birth' => '1991-01-01',
            'sex' => 'female',
            'status' => $status,
        ]);
        $patient->phone = '08030009999';
        $patient->save();

        return $patient;
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
