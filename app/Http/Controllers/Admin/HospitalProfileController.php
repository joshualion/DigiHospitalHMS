<?php

namespace App\Http\Controllers\Admin;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HospitalProfileController extends FoundationController
{
    public function edit(): Response
    {
        $hospital = $this->currentHospital();
        $this->authorize('view', $hospital);

        return Inertia::render('Admin/Hospital/Edit', [
            'hospital' => $hospital,
        ]);
    }

    public function update(Request $request, AuditService $audit): RedirectResponse
    {
        $hospital = $this->currentHospital();
        $this->authorize('update', $hospital);

        $validated = $request->validate($this->rules());
        $before = $hospital->only(array_keys($validated));

        $hospital->update($validated);

        $audit->record('hospital.updated', $hospital, $before, $hospital->only(array_keys($validated)));

        return back()->with('success', 'Hospital profile updated.');
    }

    private function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'registration_reference' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_numbers' => ['nullable', 'array'],
            'phone_numbers.*' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'timezone'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'primary_contact_name' => ['nullable', 'string', 'max:255'],
            'primary_contact_email' => ['nullable', 'email', 'max:255'],
            'primary_contact_phone' => ['nullable', 'string', 'max:50'],
            'default_currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ];
    }
}
