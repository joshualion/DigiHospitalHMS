<?php

namespace App\Http\Controllers\Admin;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends FoundationController
{
    public function index(): Response
    {
        abort_unless(request()->user()->can('roles.view') || request()->user()->hasRole('superadmin'), 403);

        return Inertia::render('Admin/Roles', [
            'roles' => Role::with('permissions:id,name')->orderBy('name')->get(['id', 'name', 'guard_name']),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
            'canManagePermissions' => request()->user()->can('permissions.manage') || request()->user()->hasRole('superadmin'),
        ]);
    }

    public function update(Request $request, Role $role, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('permissions.manage') || $request->user()->hasRole('superadmin'), 403);
        abort_if($role->name === 'superadmin' && ! $request->user()->hasRole('superadmin'), 403);

        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = ['permissions' => $role->permissions()->pluck('name')->all()];
        $role->syncPermissions($validated['permissions'] ?? []);

        $audit->record('permissions.updated', $role, $before, ['permissions' => $role->fresh()->permissions()->pluck('name')->all()], hospital: $this->currentHospital());

        return back()->with('success', 'Role permissions updated.');
    }
}
