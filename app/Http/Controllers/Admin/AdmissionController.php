<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\BedClass;
use App\Models\BillableService;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionWorkflowService;
use App\Services\AdmissionIntegrityService;
use App\Services\AdmissionsDemoResetService;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends FoundationController
{
    public function index(AdmissionIntegrityService $integrity): Response
    {
        $this->authorize('viewAny', Admission::class);
        $hospital = $this->currentHospital();
        $integrityState = $integrity->bedBoard($hospital->id);

        return Inertia::render('Admin/Admissions/Index', $this->shared($hospital->id) + [
            'admissions' => Admission::with(['patient', 'ward', 'bed', 'movements.toBed', 'invoice.lines'])->where('hospital_id', $hospital->id)->latest()->get(),
            'beds' => $integrityState['beds'],
            'wards' => Ward::with(['facility', 'department', 'beds'])->where('hospital_id', $hospital->id)->orderBy('name')->get(),
            'census' => $integrityState['census'],
            'bedIntegrityIssues' => $integrityState['issues'],
        ]);
    }

    public function storeBedClass(Request $request): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['billable_service_id' => ['nullable', Rule::exists('billable_services', 'id')->where('hospital_id', $hospital->id)], 'code' => ['required', 'string', 'max:50', Rule::unique('bed_classes')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000']]);
        BedClass::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);

        return back()->with('success', 'Bed class created.');
    }

    public function storeWard(Request $request): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)], 'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000']]);
        Ward::create($validated + ['hospital_id' => $hospital->id, 'status' => 'active']);

        return back()->with('success', 'Ward created.');
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['ward_id' => ['required', Rule::exists('wards', 'id')->where('hospital_id', $hospital->id)], 'code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:255']]);
        WardRoom::create($validated + ['hospital_id' => $hospital->id, 'status' => 'active']);

        return back()->with('success', 'Room created.');
    }

    public function storeBed(Request $request): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['ward_id' => ['required', Rule::exists('wards', 'id')->where('hospital_id', $hospital->id)], 'ward_room_id' => ['nullable', Rule::exists('ward_rooms', 'id')->where('hospital_id', $hospital->id)], 'bed_class_id' => ['required', Rule::exists('bed_classes', 'id')->where('hospital_id', $hospital->id)], 'code' => ['required', 'string', 'max:50'], 'label' => ['required', 'string', 'max:255']]);
        $ward = Ward::where('hospital_id', $hospital->id)->findOrFail($validated['ward_id']);
        Bed::create($validated + ['hospital_id' => $hospital->id, 'facility_id' => $ward->facility_id, 'state' => 'available']);

        return back()->with('success', 'Bed created.');
    }

    public function updateBedClass(Request $request, BedClass $bedClass): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        abort_unless($bedClass->hospital_id === $hospital->id, 403);

        $validated = $request->validate([
            'billable_service_id' => ['nullable', Rule::exists('billable_services', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('bed_classes')->where('hospital_id', $hospital->id)->ignore($bedClass->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        $bedClass->update($validated);

        return back()->with('success', 'Bed class updated.');
    }

    public function destroyBedClass(BedClass $bedClass): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        abort_unless($bedClass->hospital_id === $this->currentHospital()->id, 403);

        if (Bed::where('bed_class_id', $bedClass->id)->exists()) {
            return back()->withErrors(['setup' => 'This bed class is still assigned to one or more beds. Reassign or delete those beds first.']);
        }

        $bedClass->delete();

        return back()->with('success', 'Bed class deleted.');
    }

    public function updateWard(Request $request, Ward $ward): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        abort_unless($ward->hospital_id === $hospital->id, 403);

        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('wards')->where(fn ($query) => $query->where('hospital_id', $hospital->id)->where('facility_id', $request->integer('facility_id')))->ignore($ward->id)],
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $ward->update($validated);

        Bed::where('ward_id', $ward->id)->update(['facility_id' => $validated['facility_id']]);

        return back()->with('success', 'Ward updated.');
    }

    public function destroyWard(Ward $ward): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        abort_unless($ward->hospital_id === $this->currentHospital()->id, 403);

        if ($ward->beds()->exists() || $ward->rooms()->exists()) {
            return back()->withErrors(['setup' => 'Delete or move the ward beds and rooms before deleting this ward.']);
        }

        if (Admission::where('current_ward_id', $ward->id)->whereIn('status', ['admitted', 'transferred'])->exists()) {
            return back()->withErrors(['setup' => 'This ward is in use by an active admission and cannot be deleted.']);
        }

        $ward->delete();

        return back()->with('success', 'Ward deleted.');
    }

    public function updateRoom(Request $request, WardRoom $room): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        abort_unless($room->hospital_id === $hospital->id, 403);

        $validated = $request->validate([
            'ward_id' => ['required', Rule::exists('wards', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('ward_rooms')->where('ward_id', $request->integer('ward_id'))->ignore($room->id)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if ($room->beds()->exists() && (int) $validated['ward_id'] !== (int) $room->ward_id) {
            return back()->withErrors(['setup' => 'Move or delete this room’s beds before moving the room to another ward.']);
        }

        $room->update($validated);

        return back()->with('success', 'Room updated.');
    }

    public function destroyRoom(WardRoom $room): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        abort_unless($room->hospital_id === $this->currentHospital()->id, 403);

        if ($room->beds()->exists()) {
            return back()->withErrors(['setup' => 'Delete or move the beds in this room before deleting the room.']);
        }

        $room->delete();

        return back()->with('success', 'Room deleted.');
    }

    public function updateBed(Request $request, Bed $bed): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $hospital = $this->currentHospital();
        abort_unless($bed->hospital_id === $hospital->id, 403);

        $validated = $request->validate([
            'ward_id' => ['required', Rule::exists('wards', 'id')->where('hospital_id', $hospital->id)],
            'ward_room_id' => ['nullable', Rule::exists('ward_rooms', 'id')->where('hospital_id', $hospital->id)],
            'bed_class_id' => ['required', Rule::exists('bed_classes', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('beds')->where('ward_id', $request->integer('ward_id'))->ignore($bed->id)],
            'label' => ['required', 'string', 'max:255'],
        ]);

        $ward = Ward::where('hospital_id', $hospital->id)->findOrFail($validated['ward_id']);

        if (! empty($validated['ward_room_id'])) {
            $validRoom = WardRoom::where('hospital_id', $hospital->id)
                ->where('ward_id', $ward->id)
                ->whereKey($validated['ward_room_id'])
                ->exists();

            if (! $validRoom) {
                return back()->withErrors(['ward_room_id' => 'The selected room must belong to the selected ward.']);
            }
        }

        if (Admission::where('current_bed_id', $bed->id)->whereIn('status', ['admitted', 'transferred'])->exists()
            && (int) $validated['ward_id'] !== (int) $bed->ward_id) {
            return back()->withErrors(['setup' => 'An occupied bed cannot be moved to another ward. Transfer or discharge the patient first.']);
        }

        $bed->update($validated + ['facility_id' => $ward->facility_id]);

        return back()->with('success', 'Bed updated.');
    }

    public function destroyBed(Bed $bed): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        abort_unless($bed->hospital_id === $this->currentHospital()->id, 403);

        if (Admission::where('current_bed_id', $bed->id)->whereIn('status', ['admitted', 'transferred'])->exists()) {
            return back()->withErrors(['setup' => 'This bed is occupied by an active admission. Transfer or discharge the patient before deletion.']);
        }

        $bed->delete();

        return back()->with('success', 'Bed deleted.');
    }

    public function reconcileBeds(Request $request, AdmissionIntegrityService $integrity, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        $result = $integrity->reconcile($this->currentHospital(), $request->user(), $audit);

        return back()->with('success', "Bed occupancy reconciled. {$result['changed_count']} bed(s) corrected.");
    }

    public function purgeDemo(Request $request, AdmissionsDemoResetService $reset, AuditService $audit): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        abort_unless($request->user()->hasAnyRole(['superadmin', 'hospital-admin']), 403);

        $request->validate(['confirmation' => ['required', 'in:PURGE ADMISSIONS']]);

        $hospital = $this->currentHospital();

        try {
            $result = $reset->reset($hospital);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['reset' => 'Admissions demo reset could not complete safely. No partial reset was kept.']);
        }

        $audit->record('admissions.demo_reset', $hospital, null, $result, actor: $request->user(), reason: 'Explicit pre-production admissions reset');

        return back()->with('success', "Admissions demo data reset complete. {$result['deleted_rows']} record(s) removed.");
    }

    public function requestAdmission(Request $request, AdmissionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('request', Admission::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)], 'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospital->id)], 'visit_id' => ['nullable', Rule::exists('visits', 'id')->where('hospital_id', $hospital->id)], 'clinical_encounter_id' => ['nullable', Rule::exists('clinical_encounters', 'id')->where('hospital_id', $hospital->id)], 'attending_clinician_id' => ['nullable', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospital->id)], 'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)], 'reason' => ['nullable', 'string', 'max:2000'], 'provisional_diagnosis' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000'], 'administrative_clearance_required' => ['boolean']]);
        $workflow->request($validated + ['hospital_id' => $hospital->id], $request->user());

        return back()->with('success', 'Admission requested.');
    }

    public function action(Request $request, Admission $admission, AdmissionWorkflowService $workflow): RedirectResponse
    {
        abort_unless($admission->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'admit', 'transfer', 'discharge', 'cancel'])], 'bed_id' => ['nullable', Rule::exists('beds', 'id')->where('hospital_id', $admission->hospital_id)], 'reason' => ['nullable', 'string', 'max:1000'], 'discharge_destination' => ['nullable', 'string', 'max:120'], 'discharge_outcome' => ['nullable', 'string', 'max:120'], 'discharge_notes' => ['nullable', 'string', 'max:2000'], 'override' => ['boolean'], 'override_reason' => ['nullable', 'string', 'max:1000']]);
        match ($validated['action']) {
            'approve' => tap($this->authorize('approve', $admission), fn () => $workflow->approve($admission, $request->user(), $validated['reason'] ?? null)),
            'reject' => tap($this->authorize('approve', $admission), fn () => $workflow->reject($admission, $request->user(), $validated['reason'] ?? 'Rejected')),
            'admit' => tap($this->authorize('manage', $admission), fn () => $workflow->admit($admission, Bed::findOrFail($validated['bed_id']), $request->user(), $validated)),
            'transfer' => tap($this->authorize('manage', $admission), fn () => $workflow->transfer($admission, Bed::findOrFail($validated['bed_id']), $request->user(), $validated['reason'] ?? 'Transfer')),
            'discharge' => tap($this->authorize('discharge', $admission), fn () => $workflow->discharge($admission, $request->user(), $validated + ['discharged_at' => now()])),
            'cancel' => tap($this->authorize('manage', $admission), fn () => $workflow->cancel($admission, $request->user(), $validated['reason'] ?? 'Cancelled')),
        };

        return back()->with('success', 'Admission updated.');
    }

    public function bedState(Request $request, Bed $bed, AdmissionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('manage', Admission::class);
        abort_unless($bed->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['state' => ['required', Rule::in(['available', 'reserved', 'cleaning', 'maintenance', 'blocked', 'inactive'])], 'reason' => ['required', 'string', 'max:1000']]);
        $workflow->setBedState($bed, $validated['state'], $request->user(), $validated['reason']);

        return back()->with('success', 'Bed state updated.');
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'patients' => Patient::where('hospital_id', $hospitalId)->latest()->limit(50)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
            'visits' => Visit::where('hospital_id', $hospitalId)->latest()->limit(50)->get(['id', 'patient_id', 'facility_id', 'department_id', 'status']),
            'encounters' => ClinicalEncounter::where('hospital_id', $hospitalId)->latest()->limit(50)->get(['id', 'patient_id', 'visit_id', 'status']),
            'clinicians' => StaffProfile::with('user:id,firstname,lastname')->where('hospital_id', $hospitalId)->where('is_active', true)->get(['id', 'user_id', 'job_title']),
            'bedClasses' => BedClass::with('billableService:id,code,name')->where('hospital_id', $hospitalId)->where('is_active', true)->get(),
            'rooms' => WardRoom::where('hospital_id', $hospitalId)->where('status', 'active')->get(),
            'services' => BillableService::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }
}
