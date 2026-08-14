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
                ]),
                'roles' => fn () => $request->user()?->getRoleNames()->values() ?? [],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }
}
