<?php

namespace App\Http\Controllers\Admin;

use App\Models\Facility;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HospitalSettingController extends FoundationController
{
    public function edit(): Response
    {
        $hospital = $this->currentHospital();
        $setting = $hospital->settings()->firstOrCreate(['hospital_id' => $hospital->id]);
        $this->authorize('view', $setting);

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => $setting,
            'facilities' => Facility::where('hospital_id', $hospital->id)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $hospital = $this->currentHospital();
        $setting = $hospital->settings()->firstOrCreate(['hospital_id' => $hospital->id]);
        $this->authorize('update', $setting);

        $validated = $request->validate([
            'default_facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'locale' => ['required', 'string', 'max:20'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'date_format' => ['required', 'string', 'max:50'],
            'time_format' => ['required', 'string', 'max:50'],
            'branding' => ['nullable', 'array'],
            'contact_details' => ['nullable', 'array'],
            'operating_preferences' => ['nullable', 'array'],
            'public_site_defaults' => ['nullable', 'array'],
            'numbering_preferences' => ['nullable', 'array'],
        ]);

        $before = $setting->only(array_keys($validated));
        $setting->update($validated);

        $audit->record('settings.updated', $setting, $before, $setting->only(array_keys($validated)));

        return back()->with('success', 'Hospital settings updated.');
    }
}
