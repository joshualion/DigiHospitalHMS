<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AppointmentReminderService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase9BNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_appointment_email_reminder_is_sent_and_not_duplicated(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        config(['mail.default' => 'array']);

        $hospital=Hospital::factory()->create(['display_name'=>'Reminder Hospital']);
        $facility=Facility::factory()->create(['hospital_id'=>$hospital->id,'status'=>'active']);
        $user=User::factory()->create(['access_level'=>'admin']);
        $user->syncRoles(['hospital-admin']);
        $staff=StaffProfile::factory()->create([
            'user_id'=>$user->id,'hospital_id'=>$hospital->id,'staff_category'=>'doctor',
            'is_active'=>true,'employment_status'=>'active',
        ]);

        $patient=Patient::create([
            'hospital_id'=>$hospital->id,'registration_facility_id'=>$facility->id,'registered_by'=>$user->id,
            'hospital_number'=>'PAT-NOTIFY-1','first_name'=>'Ada','last_name'=>'Reminder','sex'=>'female','status'=>'active',
        ]);
        $patient->email='ada@example.test';
        $patient->save();

        $type=AppointmentType::create([
            'hospital_id'=>$hospital->id,'name'=>'Consultation','code'=>'CONS-NOTIFY',
            'duration_minutes'=>30,'is_active'=>true,
        ]);

        $appointment=Appointment::create([
            'hospital_id'=>$hospital->id,'facility_id'=>$facility->id,'patient_id'=>$patient->id,
            'clinician_id'=>$staff->id,'appointment_type_id'=>$type->id,
            'starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addMinutes(30),
            'status'=>'confirmed','source'=>'staff','booked_by'=>$user->id,
        ]);

        NotificationTemplate::create([
            'hospital_id'=>$hospital->id,'key'=>'appointment_reminder','channel'=>'email',
            'name'=>'Email reminder','subject'=>'Reminder {hospital_name}',
            'body'=>'Hello {patient_name}. Appointment: {appointment_date} {appointment_time}.',
            'reminder_minutes_before'=>1440,'is_active'=>true,
        ]);

        $this->assertTrue($user->can('notifications.view'));
        $this->assertTrue($user->can('notifications.manage'));
        $this->assertTrue($user->can('notifications.send'));

        $first=app(AppointmentReminderService::class)->sendDue($hospital);
        $second=app(AppointmentReminderService::class)->sendDue($hospital);

        $this->assertSame(1,$first['sent']);
        $this->assertSame(1,$second['skipped']);
        $this->assertDatabaseCount('notification_deliveries',1);

        $delivery=NotificationDelivery::firstOrFail();
        $this->assertSame('sent',$delivery->status);
        $this->assertSame('email',$delivery->channel);
        $this->assertSame($appointment->id,$delivery->appointment_id);
        $this->assertSame('ada@example.test',$delivery->recipient);
    }
}
