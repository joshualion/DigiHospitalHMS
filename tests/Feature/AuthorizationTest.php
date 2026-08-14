<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_authenticated_routes(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_admin_can_access_admin_routes(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['access_level' => 'admin']);
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create(['access_level' => 'patient']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }
}
