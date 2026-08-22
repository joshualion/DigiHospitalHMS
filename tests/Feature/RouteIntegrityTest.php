<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\HospitalFoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PublicSiteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RouteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_list_command_succeeds(): void
    {
        $this->assertSame(0, Artisan::call('route:list'));
    }

    public function test_public_routes_resolve(): void
    {
        $this->seed(HospitalFoundationSeeder::class);
        $this->seed(PublicSiteSeeder::class);

        foreach (['/', '/about', '/doctor', '/appointment', '/blog', '/contact', '/policies'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_admin_routes_resolve_for_admin_user(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(HospitalFoundationSeeder::class);

        $user = User::factory()->create(['access_level' => 'admin']);
        $user->assignRole('admin');

        foreach (['/admin/dashboard', '/admin/roles'] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }

        $this->actingAs($user)->get('/admin/pages')->assertGone();
        $this->actingAs($user)->get('/admin/users')->assertRedirect('/admin/staff');
    }
}
