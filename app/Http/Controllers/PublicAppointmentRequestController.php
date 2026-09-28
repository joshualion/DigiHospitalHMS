<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\PublicAppointmentRequest;
use App\Models\StaffProfile;
use App\Services\AuditService;
use App\Services\SensitiveLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicAppointmentRequestController extends Controller
{
    public function create(Request $request): Response
    {
        $hospital = Hospital::primary() ?? Hospital::firstOrFail();
        $site = app(PublicSiteController::class)->siteShell($hospital);

        $doctors = StaffProfile::with('user:id,firstname,lastname')
                ->where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->where('employment_status', 'active')
                ->where('public_is_visible', true)
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->where(function ($query): void {
                    $query->where('staff_category', 'doctor')
                        ->orWhere('job_title', 'like', '%doctor%')
                        ->orWhereHas('user.roles', fn ($roles) => $roles->where('name', 'doctor'));
                })
                ->orderBy('public_display_order')
                ->orderBy('id')
                ->get(['id', 'user_id', 'job_title', 'public_display_name', 'public_specialty']);

        $requestedDoctorId = (int) $request->query('doctor', 0);
        $selectedDoctorId = $doctors->contains(fn (StaffProfile $doctor) => $doctor->id === $requestedDoctorId)
            ? $requestedDoctorId
            : null;

        return Inertia::render('Public/AppointmentRequest', [
            'site' => $site,
            'facilities' => Facility::where('hospital_id', $hospital->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('hospital_id', $hospital->id)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'doctors' => $doctors,
            'selectedDoctorId' => $selectedDoctorId,
        ]);
    }

    public function store(Request $request, AuditService $audit, SensitiveLookup $lookup): RedirectResponse
    {
        $hospital = Hospital::primary() ?? Hospital::firstOrFail();
        $key = 'public-appointment:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many appointment requests. Please try again later.');
        RateLimiter::hit($key, 3600);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:50'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'preferred_facility_id' => ['nullable', Rule::exists('facilities', 'id')->where(fn ($query) => $query->where('hospital_id', $hospital->id)->where('status', 'active'))],
            'preferred_department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)],
            'preferred_clinician_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospital->id)],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'prohibited'],
        ]);

        if (! empty($validated['preferred_clinician_id'])) {
            $isDoctor = StaffProfile::whereKey($validated['preferred_clinician_id'])
                ->where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->where('employment_status', 'active')
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->where(function ($query): void {
                    $query->where('staff_category', 'doctor')
                        ->orWhere('job_title', 'like', '%doctor%')
                        ->orWhereHas('user.roles', fn ($roles) => $roles->where('name', 'doctor'));
                })
                ->exists();

            if (! $isDoctor) {
                return back()->withErrors(['preferred_clinician_id' => 'Please choose an active doctor.']);
            }
        }

        $appointmentRequest = new PublicAppointmentRequest([
            'hospital_id' => $hospital->id,
            'preferred_facility_id' => $validated['preferred_facility_id'] ?? null,
            'preferred_department_id' => $validated['preferred_department_id'] ?? null,
            'preferred_clinician_id' => $validated['preferred_clinician_id'] ?? null,
            'name' => $validated['name'],
            'preferred_date' => $validated['preferred_date'] ?? null,
            'consent' => true,
            'status' => 'pending',
            'ip_hash' => $lookup->hash($request->ip()),
        ]);
        $appointmentRequest->phone = $validated['phone'] ?? null;
        $appointmentRequest->email = $validated['email'] ?? null;
        $appointmentRequest->save();

        $audit->record('appointment_requests.submitted', $appointmentRequest, null, $appointmentRequest->toArray(), request: $request);

        return back()->with('success', 'Your appointment request was received. Staff will review it before scheduling.');
    }
}
