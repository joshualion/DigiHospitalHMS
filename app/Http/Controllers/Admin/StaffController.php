<?php

namespace App\Http\Controllers\Admin;

use App\Models\Facility;
use App\Models\FacilityMembership;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class StaffController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StaffProfile::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Staff/Index', [
            'filters' => $request->only(['search', 'status']),
            'facilities' => Facility::where('hospital_id', $hospital->id)->orderBy('name')->get(['id', 'name', 'code']),
            'roles' => $this->assignableRoles()->values(),
            'staff' => StaffProfile::with(['user.roles:id,name', 'memberships.facility:id,name,code'])
                ->where('hospital_id', $hospital->id)
                ->when($request->search, fn ($query, $search) => $query->where(function ($inner) use ($search): void {
                    $inner->where('staff_number', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                }))
                ->when($request->status, fn ($query, $status) => $query->where('employment_status', $status))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('create', StaffProfile::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate($this->rules($hospital->id));
        $this->guardRoleAssignment($validated['roles'] ?? []);

        $staff = DB::transaction(function () use ($validated, $hospital, $audit): StaffProfile {
            $user = User::create([
                'firstname' => $validated['firstname'],
                'lastname' => $validated['lastname'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(48)),
                'access_level' => 'patient',
                'status' => 'active',
            ]);

            $user->syncRoles($validated['roles'] ?? []);

            $staff = StaffProfile::create([
                'user_id' => $user->id,
                'hospital_id' => $hospital->id,
                'staff_number' => $validated['staff_number'],
                'job_title' => $validated['job_title'] ?? null,
                'staff_category' => $validated['staff_category'],
                'professional_license_number' => $validated['professional_license_number'] ?? null,
                'license_expires_at' => $validated['license_expires_at'] ?? null,
                'work_phone' => $validated['work_phone'] ?? null,
                'employment_status' => 'active',
                'hire_date' => $validated['hire_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
                'public_is_visible' => (bool) ($validated['public_is_visible'] ?? false),
                'public_is_featured' => (bool) ($validated['public_is_featured'] ?? false),
                'public_slug' => $this->publicSlug(
                    $validated['public_slug'] ?? null,
                    trim("{$validated['firstname']} {$validated['lastname']}"),
                    (bool) ($validated['public_is_visible'] ?? false)
                ),
                'public_display_name' => $validated['public_display_name'] ?? null,
                'public_specialty' => $validated['public_specialty'] ?? null,
                'public_summary' => $validated['public_summary'] ?? null,
                'public_photo_path' => $validated['public_photo_path'] ?? null,
                'public_photo_alt' => $validated['public_photo_alt'] ?? null,
                'public_display_order' => (int) ($validated['public_display_order'] ?? 0),
            ]);

            $this->syncMemberships($staff, $validated['facility_ids'] ?? [], $validated['default_facility_id'] ?? null);

            $audit->record('staff.invited', $staff, null, $staff->load('user.roles', 'memberships')->toArray());
            $audit->record('roles.assigned', $user, null, ['roles' => $user->getRoleNames()->all()]);

            return $staff;
        });

        Password::sendResetLink(['email' => $staff->user->email]);

        return back()->with('success', 'Staff account created and password setup link queued.');
    }

    public function update(Request $request, StaffProfile $staffProfile, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $staffProfile);
        $validated = $request->validate($this->rules($staffProfile->hospital_id, $staffProfile->id, $staffProfile->user_id));
        $this->guardRoleAssignment($validated['roles'] ?? []);

        DB::transaction(function () use ($staffProfile, $validated, $audit): void {
            $user = $staffProfile->user;
            $beforeUserRoles = $user->getRoleNames()->all();
            $before = $staffProfile->only([
                'staff_number',
                'job_title',
                'staff_category',
                'professional_license_number',
                'license_expires_at',
                'work_phone',
                'hire_date',
                'notes',
                'public_is_visible',
                'public_is_featured',
                'public_slug',
                'public_display_name',
                'public_specialty',
                'public_summary',
                'public_photo_path',
                'public_photo_alt',
                'public_display_order',
            ]);

            $user->update([
                'firstname' => $validated['firstname'],
                'lastname' => $validated['lastname'],
                'email' => $validated['email'],
            ]);

            $staffProfile->update([
                'staff_number' => $validated['staff_number'],
                'job_title' => $validated['job_title'] ?? null,
                'staff_category' => $validated['staff_category'],
                'professional_license_number' => $validated['professional_license_number'] ?? null,
                'license_expires_at' => $validated['license_expires_at'] ?? null,
                'work_phone' => $validated['work_phone'] ?? null,
                'hire_date' => $validated['hire_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'public_is_visible' => (bool) ($validated['public_is_visible'] ?? false),
                'public_is_featured' => (bool) ($validated['public_is_featured'] ?? false),
                'public_slug' => $this->publicSlug(
                    $validated['public_slug'] ?? null,
                    $user->full_name,
                    (bool) ($validated['public_is_visible'] ?? false)
                ),
                'public_display_name' => $validated['public_display_name'] ?? null,
                'public_specialty' => $validated['public_specialty'] ?? null,
                'public_summary' => $validated['public_summary'] ?? null,
                'public_photo_path' => $validated['public_photo_path'] ?? null,
                'public_photo_alt' => $validated['public_photo_alt'] ?? null,
                'public_display_order' => (int) ($validated['public_display_order'] ?? 0),
            ]);

            $user->syncRoles($validated['roles'] ?? []);
            $this->syncMemberships($staffProfile, $validated['facility_ids'] ?? [], $validated['default_facility_id'] ?? null);

            $audit->record('staff.updated', $staffProfile, $before, $staffProfile->only(array_keys($before)));
            $audit->record('roles.updated', $user, ['roles' => $beforeUserRoles], ['roles' => $user->getRoleNames()->all()]);
            $audit->record('facility_memberships.updated', $staffProfile, null, [
                'facility_ids' => $validated['facility_ids'] ?? [],
                'default_facility_id' => $validated['default_facility_id'] ?? null,
            ]);
        });

        return back()->with('success', 'Staff profile updated.');
    }

    public function status(Request $request, StaffProfile $staffProfile, AuditService $audit): RedirectResponse
    {
        $this->authorize('suspend', $staffProfile);
        $validated = $request->validate(['status' => ['required', 'in:active,suspended']]);
        $user = $staffProfile->user;

        if ($validated['status'] === 'suspended') {
            $this->ensureNotLastSuperadmin($user);
        }

        $before = [
            'user_status' => $user->status,
            'employment_status' => $staffProfile->employment_status,
        ];

        $user->update([
            'status' => $validated['status'],
            'suspended_at' => $validated['status'] === 'suspended' ? now() : null,
            'suspended_by' => $validated['status'] === 'suspended' ? $request->user()->id : null,
        ]);

        $staffProfile->update([
            'employment_status' => $validated['status'],
            'is_active' => $validated['status'] === 'active',
        ]);

        $audit->record("staff.{$validated['status']}", $staffProfile, $before, [
            'user_status' => $user->status,
            'employment_status' => $staffProfile->employment_status,
        ]);

        return back()->with('success', 'Staff account status updated.');
    }

    private function rules(int $hospitalId, ?int $staffProfileId = null, ?int $userId = null): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'staff_number' => ['required', 'string', 'max:50', Rule::unique('staff_profiles', 'staff_number')->where('hospital_id', $hospitalId)->ignore($staffProfileId)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'staff_category' => ['required', 'string', 'max:100'],
            'professional_license_number' => ['nullable', 'string', 'max:255'],
            'license_expires_at' => ['nullable', 'date'],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'hire_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'public_is_visible' => ['boolean'],
            'public_is_featured' => ['boolean'],
            'public_slug' => ['nullable', 'string', 'max:255', Rule::unique('staff_profiles', 'public_slug')->where('hospital_id', $hospitalId)->ignore($staffProfileId)],
            'public_display_name' => ['nullable', 'string', 'max:255'],
            'public_specialty' => ['nullable', 'string', 'max:255'],
            'public_summary' => ['nullable', 'string', 'max:2000'],
            'public_photo_path' => ['nullable', 'string', 'max:255'],
            'public_photo_alt' => ['nullable', 'string', 'max:255'],
            'public_display_order' => ['integer', 'min:0'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'facility_ids' => ['required', 'array', 'min:1'],
            'facility_ids.*' => [Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
            'default_facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
        ];
    }

    private function syncMemberships(StaffProfile $staff, array $facilityIds, ?int $defaultFacilityId): void
    {
        abort_if($defaultFacilityId && ! in_array($defaultFacilityId, $facilityIds, true), 422, 'Default facility must be one of the assigned facilities.');

        FacilityMembership::where('staff_profile_id', $staff->id)
            ->whereNotIn('facility_id', $facilityIds)
            ->update(['status' => 'inactive', 'is_default' => false]);

        foreach ($facilityIds as $facilityId) {
            FacilityMembership::updateOrCreate(
                ['staff_profile_id' => $staff->id, 'facility_id' => $facilityId],
                ['status' => 'active', 'is_default' => (int) $facilityId === (int) $defaultFacilityId]
            );
        }

        if (! $defaultFacilityId && $facilityIds !== []) {
            FacilityMembership::where('staff_profile_id', $staff->id)->where('facility_id', $facilityIds[0])->update(['is_default' => true]);
        }
    }

    private function assignableRoles()
    {
        $query = Role::query()->orderBy('name');

        if (! request()->user()->hasRole('superadmin')) {
            $query->whereNotIn('name', ['superadmin']);
        }

        return $query->get(['id', 'name']);
    }

    private function guardRoleAssignment(array $roles): void
    {
        abort_if(! request()->user()->hasRole('superadmin') && in_array('superadmin', $roles, true), 403, 'Only superadministrators can assign the superadministrator role.');
    }

    private function ensureNotLastSuperadmin(User $user): void
    {
        if (! $user->hasRole('superadmin')) {
            return;
        }

        $count = User::role('superadmin')->where('status', 'active')->count();

        abort_if($count <= 1, 422, 'The final active superadministrator cannot be suspended.');
    }

    private function publicSlug(?string $slug, string $fallback, bool $isPublic): ?string
    {
        if (! $isPublic && blank($slug)) {
            return null;
        }

        if (blank($slug) && blank($fallback)) {
            return null;
        }

        return Str::slug($slug ?: $fallback);
    }
}
