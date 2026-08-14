<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hospital;

abstract class FoundationController extends Controller
{
    protected function currentHospital(): Hospital
    {
        $user = request()->user();

        if ($user?->hospitalId()) {
            return Hospital::query()->findOrFail($user->hospitalId());
        }

        return Hospital::primary() ?? Hospital::query()->create([
            'legal_name' => 'Demo Hospital',
            'display_name' => 'Demo Hospital',
            'country' => 'Nigeria',
            'timezone' => 'Africa/Lagos',
            'default_currency' => 'NGN',
        ]);
    }
}
