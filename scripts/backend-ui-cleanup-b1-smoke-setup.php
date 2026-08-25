<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

$password = getenv('CLEANUP_A_PASSWORD') ?: 'CleanupASmoke!';
$admin = User::query()->updateOrCreate(
    ['email' => 'cleanup-a-admin@example.test'],
    ['firstname' => 'Cleanup', 'lastname' => 'Admin', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active'],
);
$admin->forceFill(['email_verified_at' => now()])->save();
$admin->syncRoles(['admin']);
$admin->syncPermissions(Permission::query()->pluck('name')->all());

File::ensureDirectoryExists(storage_path('app/backend-ui-cleanup-b1'));
File::put(storage_path('app/backend-ui-cleanup-b1/context.json'), json_encode(['email' => $admin->email, 'password' => $password], JSON_PRETTY_PRINT));

echo "Backend UI Cleanup B1 smoke setup ready.\n";
