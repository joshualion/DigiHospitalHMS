<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hospital;

abstract class FoundationController extends Controller
{
    protected function currentHospital(): Hospital
    {
        $user = request()->user();
        abort_unless($user && $user->isActive(), 403, 'An active account is required.');

        $hospitalId = $user->hospitalId();
        if ($hospitalId !== null) {
            return Hospital::query()->findOrFail($hospitalId);
        }

        // Global superadministrators may administer the primary hospital, but
        // unassigned standard users must never inherit a hospital implicitly.
        if ($user->hasRole('superadmin')) {
            return Hospital::primary()
                ?? abort(404, 'Hospital setup is required before using administration.');
        }

        abort(403, 'Your account has no assigned hospital.');
    }
}
