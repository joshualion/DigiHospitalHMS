<?php

use App\Models\Facility;
use App\Models\FacilityMembership;
use App\Models\Hospital;
use App\Models\PublicSitePage;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('foundation:bootstrap-admin
    {--email= : Existing or new administrator email address}
    {--firstname= : First name when creating the user}
    {--lastname= : Last name when creating the user}
    {--password= : Password when creating the user; omit to be prompted securely}
    {--hospital= : Hospital id; defaults to the first hospital}
    {--facility= : Facility id; defaults to the primary or first facility for the hospital}
    {--force-production : Permit production execution after confirmation}', function (): int {
    if (app()->isProduction() && ! $this->option('force-production')) {
        $this->error('Refusing to bootstrap an administrator in production without --force-production.');

        return 1;
    }

    if (app()->isProduction() && ! $this->confirm('This will modify production administrator access. Continue?', false)) {
        $this->warn('Bootstrap cancelled.');

        return 1;
    }

    $activeSuperadmins = User::role('superadmin')->where('status', 'active')->count();

    if ($activeSuperadmins > 0) {
        $this->error('An active superadministrator already exists. Bootstrap refused.');

        return 1;
    }

    $email = Str::lower(trim($this->option('email') ?: $this->ask('Administrator email')));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('A valid administrator email address is required.');

        return 1;
    }

    $hospital = Hospital::query()
        ->when($this->option('hospital'), fn ($query, $id) => $query->whereKey($id))
        ->oldest('id')
        ->first();

    if (! $hospital) {
        $this->error('No hospital exists. Run the inspected foundation seeder or create the hospital deliberately first.');

        return 1;
    }

    $facility = Facility::query()
        ->where('hospital_id', $hospital->id)
        ->when($this->option('facility'), fn ($query, $id) => $query->whereKey($id))
        ->orderByDesc('is_primary')
        ->oldest('id')
        ->first();

    if (! $facility) {
        $this->error('No facility exists for the selected hospital.');

        return 1;
    }

    if (! Role::query()->where('name', 'superadmin')->where('guard_name', 'web')->exists()) {
        $this->error('The superadmin role does not exist. Run the role and permission seeders first.');

        return 1;
    }

    $user = DB::transaction(function () use ($email, $hospital, $facility): User {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $password = $this->option('password') ?: $this->secret('Password for the new administrator');
            $confirmation = $this->option('password') ?: $this->secret('Confirm password');

            if (! is_string($password) || strlen($password) < 12 || $password !== $confirmation) {
                throw new RuntimeException('Password must be at least 12 characters and match confirmation.');
            }

            $user = User::create([
                'firstname' => $this->option('firstname') ?: $this->ask('First name'),
                'lastname' => $this->option('lastname') ?: $this->ask('Last name'),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'access_level' => 'superadmin',
                'status' => 'active',
            ]);
        } else {
            $user->forceFill([
                'access_level' => 'superadmin',
                'status' => 'active',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        $staff = StaffProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'hospital_id' => $hospital->id,
                'staff_number' => 'BOOT-'.$user->id,
                'job_title' => 'Superadministrator',
                'staff_category' => 'administrative',
                'employment_status' => 'active',
                'hire_date' => now()->toDateString(),
                'notes' => 'Created by local foundation bootstrap command.',
                'is_active' => true,
            ],
        );

        $staff->forceFill([
            'hospital_id' => $hospital->id,
            'employment_status' => 'active',
            'is_active' => true,
        ])->save();

        FacilityMembership::updateOrCreate(
            [
                'staff_profile_id' => $staff->id,
                'facility_id' => $facility->id,
            ],
            [
                'facility_title' => 'Superadministrator',
                'is_default' => true,
                'status' => 'active',
                'starts_at' => now()->toDateString(),
            ],
        );

        $user->syncRoles(['superadmin']);

        app(AuditService::class)->record(
            'foundation.bootstrap_superadministrator',
            $staff,
            metadata: [
                'user_id' => $user->id,
                'hospital_id' => $hospital->id,
                'facility_id' => $facility->id,
                'environment' => app()->environment(),
            ],
            hospital: $hospital,
            facility: $facility,
            actor: $user,
        );

        return $user;
    });

    $this->info("Superadministrator bootstrap complete for {$user->email}.");

    return 0;
})->purpose('Safely bootstrap the first local hospital superadministrator');

Artisan::command('public-site:hero-smoke-state {state}', function (string $state): int {
    if (app()->isProduction()) {
        $this->error('Refusing to modify public-site hero smoke state in production.');

        return 1;
    }

    $path = storage_path('app/public-launch-cleanup-a-hero-backup.json');
    $page = PublicSitePage::query()->where('slug', 'home')->oldest('id')->first();
    $section = $page?->sections()->where('key', 'hero')->first();

    if (! $page || ! $section) {
        $this->error('Home hero section was not found.');

        return 1;
    }

    if ($state === 'backup') {
        file_put_contents($path, json_encode([
            'draft_content' => $section->draft_content,
            'published_content' => $section->published_content,
        ], JSON_PRETTY_PRINT));
        $this->info('Hero smoke backup saved.');

        return 0;
    }

    if ($state === 'restore') {
        if (! file_exists($path)) {
            $this->warn('No hero smoke backup found.');

            return 0;
        }

        $backup = json_decode((string) file_get_contents($path), true);
        $section->forceFill([
            'draft_content' => $backup['draft_content'] ?? [],
            'published_content' => $backup['published_content'] ?? [],
        ])->save();
        @unlink($path);
        $this->info('Hero smoke backup restored.');

        return 0;
    }

    $slides = match ($state) {
        'zero' => [],
        'one' => [
            ['label' => 'Smoke test', 'headline' => 'Hero smoke test', 'text' => 'Temporary verification content.', 'image' => '/frontend/images/slider/1.png', 'alt' => 'Hero smoke image', 'active' => true],
        ],
        'multiple' => [
            ['label' => 'Smoke test', 'headline' => 'First hero smoke test', 'text' => 'Temporary verification content.', 'image' => '/frontend/images/slider/1.png', 'alt' => 'First hero smoke image', 'active' => true],
            ['label' => 'Smoke test', 'headline' => 'Second hero smoke test', 'text' => 'Temporary verification content.', 'image' => '/frontend/images/slider/2.jpg', 'alt' => 'Second hero smoke image', 'active' => true],
        ],
        'broken' => [
            ['label' => 'Smoke test', 'headline' => 'Broken image smoke test', 'text' => 'Temporary verification content.', 'image' => '/frontend/images/slider/missing-launch-smoke.jpg', 'alt' => 'Broken hero smoke image', 'active' => true],
        ],
        default => null,
    };

    if ($slides === null) {
        $this->error('State must be backup, restore, zero, one, multiple, or broken.');

        return 1;
    }

    $content = ['rotation_ms' => 6500, 'slides' => $slides];
    $section->forceFill([
        'draft_content' => $content,
        'published_content' => $content,
    ])->save();
    $this->info("Hero smoke state set to {$state}.");

    return 0;
})->purpose('Switch local public-site hero states for launch cleanup browser smoke tests');
