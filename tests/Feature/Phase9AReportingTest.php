<?php

namespace Tests\Feature;

use App\Models\Facility;
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
            ->assertInertia(fn($page)=>$page->component('Admin/Reports/Index')->has('summary')->has('daily')->has('filters'));

        $this->actingAs($user)->get('/admin/reports/export?type=operations')
            ->assertOk()
            ->assertHeader('content-type','text/csv; charset=UTF-8');
    }
}
