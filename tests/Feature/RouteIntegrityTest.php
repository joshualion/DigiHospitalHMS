<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
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
        foreach (['/', '/about', '/doctor', '/appointment', '/blog', '/contact', '/policies'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_admin_routes_resolve_for_admin_user(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['access_level' => 'admin']);
        $user->assignRole('admin');

        foreach (['/admin/dashboard', '/admin/users', '/admin/roles', '/admin/pages'] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }
}
