<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Visit;
use App\Models\Patient;
use Inertia\Testing\AssertableInertia as Assert;
use App\Models\Hospital;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase9AReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_admin_can_view_and_export_reports(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $hospital=Hospital::factory()->create();
        Facility::factory()->create(['hospital_id'=>$hospital->id,'status'=>'active']);

        $user=User::factory()->create(['access_level'=>'admin']);
        $user->syncRoles(['hospital-admin']);
        StaffProfile::factory()->create([
            'user_id'=>$user->id,
            'hospital_id'=>$hospital->id,
            'staff_category'=>'administrative',
            'is_active'=>true,
            'employment_status'=>'active',
        ]);

        $this->assertTrue($user->can('reports.view'));
        $this->assertTrue($user->can('reports.export'));

        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->actingAs($user)->get('/admin/reports')
            ->assertOk()
            ->assertInertia(fn($page)=>$page->component('Admin/Reports/Index')->has('summary')->has('daily')->has('filters')->has('trends.visits', 30));

        $this->actingAs($user)->get('/admin/reports/export?type=operations')
            ->assertOk()
            ->assertHeader('content-type','text/csv; charset=UTF-8');
    }
    public function test_report_trends_do_not_include_other_hospital_activity(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $hospital = Hospital::factory()->create();
        $other = Hospital::factory()->create();
        $facility = Facility::factory()->create(['hospital_id' => $hospital->id]);
        $otherFacility = Facility::factory()->create(['hospital_id' => $other->id]);
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->syncRoles(['hospital-admin']);
        StaffProfile::factory()->create(['user_id' => $user->id, 'hospital_id' => $hospital->id]);

        $this->actingAs($user)->get('/admin/reports?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports/Index')
                ->where('summary.visits', 0)
                ->where('trends.visits.0', 0)
                ->has('daily', 1));
    }

    public function test_non_exporter_cannot_download_reports_and_range_is_limited(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $hospital = Hospital::factory()->create();
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->syncRoles(['nurse']);
        $user->givePermissionTo('reports.view');
        StaffProfile::factory()->create(['user_id' => $user->id, 'hospital_id' => $hospital->id]);

        $this->actingAs($user)->get('/admin/reports')->assertOk();
        $this->actingAs($user)->get('/admin/reports/export?type=financial')->assertForbidden();
        $this->actingAs($user)->get('/admin/reports?from=2024-01-01&to=2026-01-01')->assertStatus(422);
    }
}
