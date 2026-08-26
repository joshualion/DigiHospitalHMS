<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => fn () => $request->user()?->only([
                    'id',
                    'firstname',
                    'lastname',
                    'full_name',
                    'email',
                    'email_verified_at',
                    'access_level',
                    'status',
                ]),
                'roles' => fn () => $request->user()?->getRoleNames()->values() ?? [],
                'permissions' => fn () => $request->user()?->getAllPermissions()->pluck('name')->values() ?? [],
                'hospital' => fn () => $request->user()?->staffProfile?->hospital?->only(['id', 'display_name']),
                'facilities' => fn () => $request->user()?->staffProfile?->memberships()
                    ->with('facility:id,name,code')
                    ->where('status', 'active')
                    ->get()
                    ->map(fn ($membership) => [
                        'id' => $membership->facility->id,
                        'name' => $membership->facility->name,
                        'code' => $membership->facility->code,
                        'is_default' => $membership->is_default,
                    ]) ?? [],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
                'uploaded_media' => fn () => $request->session()->get('uploaded_media'),
                'duplicate_warnings' => fn () => $request->session()->get('duplicate_warnings') ?? [],
            ],
        ]);
    }
}
