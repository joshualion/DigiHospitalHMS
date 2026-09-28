<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private Facility $facility;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Notification::fake();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['is_active' => true, 'status' => 'active']);
        $this->facility = Facility::factory()->create([
            'hospital_id' => $this->hospital->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['access_level' => 'admin', 'status' => 'active']);
        $this->admin->syncRoles(['hospital-admin']);

        StaffProfile::factory()->create([
            'user_id' => $this->admin->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'is_active' => true,
            'employment_status' => 'active',
        ]);
    }

    public function test_staff_photo_can_be_uploaded_when_creating_any_staff_category(): void
    {
        $photo = $this->png('receptionist.png');

        $this->actingAs($this->admin)
            ->post('/admin/staff', $this->staffPayload([
                'email' => 'receptionist@example.test',
                'staff_number' => 'REC-001',
                'staff_category' => 'administrative',
                'public_photo_upload' => $photo,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $staff = StaffProfile::where('staff_number', 'REC-001')->firstOrFail();

        $this->assertNotNull($staff->public_photo_path);
        $this->assertStringStartsWith('/storage/staff/'.$this->hospital->id.'/', $staff->public_photo_path);

        Storage::disk('public')->assertExists($this->diskPath($staff->public_photo_path));
    }

    public function test_editing_staff_can_replace_existing_uploaded_photo(): void
    {
        $staff = $this->createStaffWithPhoto('doctor-old.png');
        $oldPath = $staff->public_photo_path;

        $this->actingAs($this->admin)
            ->post("/admin/staff/{$staff->id}", $this->staffPayload([
                '_method' => 'patch',
                'email' => $staff->user->email,
                'staff_number' => $staff->staff_number,
                'staff_category' => 'doctor',
                'roles' => ['doctor'],
                'public_is_visible' => true,
                'public_photo_path' => $oldPath,
                'public_photo_upload' => $this->png('doctor-new.png'),
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $staff->refresh();

        $this->assertNotSame($oldPath, $staff->public_photo_path);
        Storage::disk('public')->assertMissing($this->diskPath($oldPath));
        Storage::disk('public')->assertExists($this->diskPath($staff->public_photo_path));
    }

    public function test_editing_staff_can_remove_existing_uploaded_photo(): void
    {
        $staff = $this->createStaffWithPhoto('doctor-remove.png');
        $oldPath = $staff->public_photo_path;

        $this->actingAs($this->admin)
            ->post("/admin/staff/{$staff->id}", $this->staffPayload([
                '_method' => 'patch',
                'email' => $staff->user->email,
                'staff_number' => $staff->staff_number,
                'staff_category' => 'doctor',
                'roles' => ['doctor'],
                'public_is_visible' => true,
                'public_photo_path' => $oldPath,
                'remove_public_photo' => true,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($staff->fresh()->public_photo_path);
        Storage::disk('public')->assertMissing($this->diskPath($oldPath));
    }

    private function createStaffWithPhoto(string $filename): StaffProfile
    {
        $this->actingAs($this->admin)
            ->post('/admin/staff', $this->staffPayload([
                'email' => uniqid('doctor-', true).'@example.test',
                'staff_number' => 'DOC-'.strtoupper(substr(md5($filename), 0, 6)),
                'staff_category' => 'doctor',
                'roles' => ['doctor'],
                'public_is_visible' => true,
                'public_photo_upload' => $this->png($filename),
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return StaffProfile::latest('id')->firstOrFail();
    }

    private function staffPayload(array $overrides = []): array
    {
        return array_replace([
            'firstname' => 'Photo',
            'lastname' => 'Staff',
            'email' => 'photo-staff@example.test',
            'staff_number' => 'PHOTO-001',
            'job_title' => 'Staff Member',
            'staff_category' => 'administrative',
            'professional_license_number' => null,
            'license_expires_at' => null,
            'work_phone' => null,
            'hire_date' => null,
            'roles' => [],
            'facility_ids' => [$this->facility->id],
            'facility_departments' => [],
            'default_facility_id' => $this->facility->id,
            'notes' => null,
            'public_is_visible' => false,
            'public_is_featured' => false,
            'public_slug' => null,
            'public_display_name' => null,
            'public_specialty' => null,
            'public_summary' => null,
            'public_photo_path' => null,
            'public_photo_alt' => null,
            'public_display_order' => 0,
            'remove_public_photo' => false,
        ], $overrides);
    }

    private function png(string $name): UploadedFile
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZV0QAAAAASUVORK5CYII='
        );

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function diskPath(string $publicPath): string
    {
        return ltrim(str_replace('/storage/', '', $publicPath), '/');
    }
}
