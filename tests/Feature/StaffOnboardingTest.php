<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_created_staff_has_unique_unknown_password_and_invitation_tracking(): void
    {
        $hospital = Hospital::factory()->create(['is_active' => true, 'status' => 'active']);
        $facility = Facility::factory()->create(['hospital_id' => $hospital->id, 'status' => 'active']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->syncRoles(['hospital-admin']);
        StaffProfile::factory()->create([
            'user_id' => $admin->id, 'hospital_id' => $hospital->id,
            'employment_status' => 'active', 'is_active' => true,
        ]);

        $this->actingAs($admin)->post('/admin/staff', [
            'firstname' => 'Invited',
            'lastname' => 'Employee',
            'email' => 'invited-staff@example.test',
            'staff_number' => 'INV-001',
            'staff_category' => 'administrative',
            'roles' => ['receptionist'],
            'facility_ids' => [$facility->id],
            'default_facility_id' => $facility->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $staff = StaffProfile::where('staff_number', 'INV-001')->firstOrFail();
        $this->assertNotNull($staff->invitation_sent_at);
        $this->assertNull($staff->password_setup_at);
        $this->assertFalse(Hash::check('password', $staff->user->password));

        $this->travel(61)->seconds(); // Laravel password broker throttles immediate resend.
        $this->actingAs($admin)->post("/admin/staff/{$staff->id}/resend-invitation")
            ->assertRedirect()->assertSessionHasNoErrors();
    }
}
