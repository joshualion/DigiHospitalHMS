<?php

namespace App\Http\Controllers\Admin;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ClinicianSchedule;
use App\Models\ClinicianUnavailability;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\PublicAppointmentRequest;
use App\Models\QueueEntry;
use App\Models\StaffProfile;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentWorkflowService;
use App\Services\AuditService;
use App\Services\QueueWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Appointments/Index', $this->shared($hospital->id) + [
            'filters' => $request->only(['date', 'status', 'clinician_id']),
            'appointments' => Appointment::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'clinician.user:id,firstname,lastname', 'facility:id,name', 'department:id,name', 'type:id,name'])
                ->where('hospital_id', $hospital->id)
                ->when($request->date, fn ($query, $date) => $query->whereDate('starts_at', $date))
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->when($request->clinician_id, fn ($query, $id) => $query->where('clinician_id', $id))
                ->orderBy('starts_at')
                ->paginate(12)
                ->withQueryString(),
            'requests' => PublicAppointmentRequest::where('hospital_id', $hospital->id)->where('status', 'pending')->latest()->limit(8)->get(),
        ]);
    }

    public function queue(Request $request): Response
    {
        $this->authorize('viewAny', QueueEntry::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Appointments/Queue', $this->shared($hospital->id) + [
            'filters' => $request->only(['facility_id', 'department_id']),
            'queue' => QueueEntry::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'clinician.user:id,firstname,lastname'])
                ->where('hospital_id', $hospital->id)
                ->whereDate('queue_date', today())
                ->when($request->facility_id, fn ($query, $id) => $query->where('facility_id', $id))
                ->when($request->department_id, fn ($query, $id) => $query->where('department_id', $id))
                ->whereNot('status', 'removed')
                ->orderBy('priority')
                ->orderBy('queue_number')
                ->get(),
        ]);
    }

    public function availability(Request $request, AppointmentAvailabilityService $availability): array
    {
        $this->authorize('viewAny', Appointment::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'clinician_id' => ['required', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospital->id)],
            'appointment_type_id' => ['required', Rule::exists('appointment_types', 'id')->where('hospital_id', $hospital->id)],
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'date' => ['required', 'date'],
        ]);

        return ['slots' => $availability->slots($hospital, StaffProfile::findOrFail($validated['clinician_id']), AppointmentType::findOrFail($validated['appointment_type_id']), $validated['date'], (int) $validated['facility_id'])];
    }

    public function store(Request $request, AppointmentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('create', Appointment::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate($this->appointmentRules($hospital->id));
        $patient = Patient::where('hospital_id', $hospital->id)->findOrFail($validated['patient_id']);
        $workflow->book($validated, $patient, $request->user());

        return back()->with('success', 'Appointment booked.');
    }

    public function transition(Request $request, Appointment $appointment, AppointmentWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'cancel', 'no_show', 'reschedule'])],
            'reason' => ['nullable', 'required_if:action,cancel,no_show,reschedule', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'required_if:action,reschedule', 'date'],
        ]);

        $changes = [];
        if ($validated['action'] === 'reschedule') {
            $startsAt = Carbon::parse($validated['starts_at'])->toDateTimeString();
            $changes['starts_at'] = $startsAt;
            $changes['ends_at'] = Carbon::parse($startsAt)->addMinutes($appointment->type->duration_minutes)->toDateTimeString();
        }

        $workflow->transition($appointment, $validated['action'], $request->user(), $validated['reason'] ?? null, $changes);

        return back()->with('success', 'Appointment updated.');
    }

    public function checkIn(Request $request, Appointment $appointment, QueueWorkflowService $queue): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $queue->checkIn($appointment->patient, [
            'facility_id' => $appointment->facility_id,
            'department_id' => $appointment->department_id,
            'clinician_id' => $appointment->clinician_id,
        ], $request->user(), $appointment);

        return redirect()->route('admin.queues.index')->with('success', 'Patient checked in.');
    }

    public function walkIn(Request $request, QueueWorkflowService $queue): RedirectResponse
    {
        $this->authorize('create', Appointment::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospital->id)],
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)],
            'clinician_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospital->id)],
        ]);
        $patient = Patient::where('hospital_id', $hospital->id)->findOrFail($validated['patient_id']);
        $queue->checkIn($patient, $validated, $request->user());

        return redirect()->route('admin.queues.index')->with('success', 'Walk-in checked in.');
    }

    public function queueTransition(Request $request, QueueEntry $queueEntry, QueueWorkflowService $workflow): RedirectResponse
    {
        $action = $request->input('action');
        $this->authorize($action === 'priority' ? 'changePriority' : 'update', $queueEntry);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['call', 'recall', 'transfer', 'skip', 'remove', 'priority'])],
            'reason' => ['nullable', 'required_if:action,transfer,skip,remove,priority', 'string', 'max:1000'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $queueEntry->hospital_id)],
            'clinician_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('hospital_id', $queueEntry->hospital_id)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $changes = [];
        if ($validated['action'] === 'transfer') {
            $changes = ['department_id' => $validated['department_id'] ?? $queueEntry->department_id, 'clinician_id' => $validated['clinician_id'] ?? $queueEntry->clinician_id];
        }
        if ($validated['action'] === 'priority') {
            $changes = ['priority' => $validated['priority'] ?? $queueEntry->priority];
        }

        $workflow->transition($queueEntry, $validated['action'], $request->user(), $validated['reason'] ?? null, $changes);

        return back()->with('success', 'Queue updated.');
    }

    public function requestReview(Request $request, PublicAppointmentRequest $appointmentRequest, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->can('appointment-requests.review') || $request->user()->hasRole('superadmin'), 403);
        abort_unless($request->user()->hospitalId() === $appointmentRequest->hospital_id || $request->user()->hasRole('superadmin'), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined'])],
            'patient_id' => ['nullable', Rule::exists('patients', 'id')->where('hospital_id', $appointmentRequest->hospital_id)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $appointmentRequest->toArray();
        $appointmentRequest->update([
            'status' => $validated['status'],
            'patient_id' => $validated['patient_id'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_reason' => $validated['reason'] ?? null,
        ]);
        $audit->record("appointment_requests.{$validated['status']}", $appointmentRequest, $before, $appointmentRequest->fresh()->toArray(), actor: $request->user(), reason: $validated['reason'] ?? null);

        return back()->with('success', 'Request reviewed.');
    }

    public function storeSchedule(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('create', Appointment::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)],
            'staff_profile_id' => ['required', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospital->id)],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'breaks' => ['array'],
        ]);
        $schedule = ClinicianSchedule::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        $audit->record('clinician_schedules.created', $schedule, null, $schedule->toArray());

        return back()->with('success', 'Schedule saved.');
    }

    public function storeUnavailability(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('create', Appointment::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'staff_profile_id' => ['required', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospital->id)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $unavailable = ClinicianUnavailability::create($validated + ['hospital_id' => $hospital->id, 'recorded_by' => $request->user()->id]);
        $audit->record('clinician_unavailability.created', $unavailable, null, $unavailable->toArray());

        return back()->with('success', 'Unavailability saved.');
    }

    private function appointmentRules(int $hospitalId): array
    {
        return [
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospitalId)],
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospitalId)],
            'clinician_id' => ['required', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospitalId)],
            'appointment_type_id' => ['required', Rule::exists('appointment_types', 'id')->where('hospital_id', $hospitalId)],
            'starts_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', Rule::in(['staff', 'public_request'])],
        ];
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => Department::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'clinicians' => StaffProfile::with('user:id,firstname,lastname')->where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('id')->get(['id', 'user_id', 'job_title']),
            'appointmentTypes' => AppointmentType::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'duration_minutes']),
            'patients' => Patient::where('hospital_id', $hospitalId)->where('status', 'active')->latest()->limit(25)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
        ];
    }
}
