<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\LabReferenceRange;
use App\Models\LabRequest;
use App\Models\LabRequestTest;
use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Models\LabSpecimenType;
use App\Models\LabTest;
use App\Models\LabTestComponent;
use App\Models\LabTestProfile;
use App\Models\LabUnit;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\AuditService;
use App\Services\LaboratoryWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LaboratoryController extends FoundationController
{
    public function catalogue(): Response
    {
        $this->authorize('viewAny', LabTest::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Laboratory/Catalogue', $this->shared($hospital->id) + [
            'tests' => LabTest::with(['components.unit', 'components.referenceRanges', 'specimenType', 'billableService:id,code,name'])->where('hospital_id', $hospital->id)->orderBy('name')->get(),
            'profiles' => LabTestProfile::with('tests:id,code,name')->where('hospital_id', $hospital->id)->orderBy('name')->get(),
        ]);
    }

    public function storeSpecimenType(Request $request): RedirectResponse
    {
        $this->authorize('create', LabTest::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['code' => ['required', 'string', 'max:40', Rule::unique('lab_specimen_types')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'collection_notes' => ['nullable', 'string', 'max:2000']]);
        $type = LabSpecimenType::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('lab.specimen_type_created', $type, null, $type->toArray(), actor: $request->user());

        return back()->with('success', 'Specimen type created.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $this->authorize('create', LabTest::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['code' => ['required', 'string', 'max:40', Rule::unique('lab_units')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255']]);
        $unit = LabUnit::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('lab.unit_created', $unit, null, $unit->toArray(), actor: $request->user());

        return back()->with('success', 'Unit created.');
    }

    public function storeTest(Request $request): RedirectResponse
    {
        $this->authorize('create', LabTest::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)],
            'default_specimen_type_id' => ['nullable', Rule::exists('lab_specimen_types', 'id')->where('hospital_id', $hospital->id)],
            'billable_service_id' => ['nullable', Rule::exists('billable_services', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:40', Rule::unique('lab_tests')->where('hospital_id', $hospital->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'turnaround_time' => ['nullable', 'string', 'max:100'],
            'requires_approval' => ['boolean'],
        ]);
        $test = LabTest::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('lab.test_created', $test, null, $test->toArray(), actor: $request->user());

        return back()->with('success', 'Laboratory test created.');
    }

    public function storeComponent(Request $request, LabTest $test): RedirectResponse
    {
        $this->authorize('update', $test);
        $validated = $request->validate(['lab_unit_id' => ['nullable', Rule::exists('lab_units', 'id')->where('hospital_id', $test->hospital_id)], 'code' => ['required', 'string', 'max:40', Rule::unique('lab_test_components')->where('lab_test_id', $test->id)], 'name' => ['required', 'string', 'max:255'], 'result_type' => ['required', Rule::in(['numeric', 'text', 'qualitative', 'comment'])], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        $component = LabTestComponent::create($validated + ['hospital_id' => $test->hospital_id, 'lab_test_id' => $test->id, 'is_required' => true, 'is_active' => true]);
        app(AuditService::class)->record('lab.component_created', $component, null, $component->toArray(), actor: $request->user());

        return back()->with('success', 'Component created.');
    }

    public function storeReferenceRange(Request $request, LabTestComponent $component): RedirectResponse
    {
        abort_unless($request->user()->can('lab.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        $validated = $request->validate(['label' => ['required', 'string', 'max:255'], 'low_value' => ['nullable', 'numeric'], 'high_value' => ['nullable', 'numeric'], 'critical_low_value' => ['nullable', 'numeric'], 'critical_high_value' => ['nullable', 'numeric'], 'qualitative_normal' => ['nullable', 'string', 'max:255'], 'display_text' => ['nullable', 'string', 'max:2000']]);
        $range = LabReferenceRange::create($validated + ['hospital_id' => $component->hospital_id, 'lab_test_component_id' => $component->id, 'requires_professional_validation' => true, 'is_active' => true]);
        app(AuditService::class)->record('lab.reference_range_created', $range, null, $range->toArray(), actor: $request->user());

        return back()->with('success', 'Reference range created.');
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $this->authorize('create', LabTest::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['code' => ['required', 'string', 'max:40', Rule::unique('lab_test_profiles')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'lab_test_ids' => ['array'], 'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('hospital_id', $hospital->id)]]);
        $testIds = $validated['lab_test_ids'] ?? [];
        unset($validated['lab_test_ids']);
        $profile = LabTestProfile::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        $profile->tests()->sync($testIds);
        app(AuditService::class)->record('lab.profile_created', $profile, null, $profile->load('tests')->toArray(), actor: $request->user());

        return back()->with('success', 'Panel created.');
    }

    public function updateSpecimenType(Request $request, LabSpecimenType $specimenType): RedirectResponse
    {
        abort_unless($request->user()->can('lab.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        abort_unless($specimenType->hospital_id === $this->currentHospital()->id, 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('lab_specimen_types')->where('hospital_id', $specimenType->hospital_id)->ignore($specimenType->id)],
            'name' => ['required', 'string', 'max:255'],
            'collection_notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
        $before = $specimenType->toArray();
        $specimenType->update($validated);
        app(AuditService::class)->record('lab.specimen_type_updated', $specimenType, $before, $specimenType->fresh()->toArray(), actor: $request->user());

        return back()->with('success', 'Specimen type updated.');
    }

    public function destroySpecimenType(Request $request, LabSpecimenType $specimenType): RedirectResponse
    {
        abort_unless($request->user()->can('lab.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        abort_unless($specimenType->hospital_id === $this->currentHospital()->id, 403);

        if (DB::table('lab_specimens')->where('lab_specimen_type_id', $specimenType->id)->exists()) {
            return back()->withErrors(['catalogue' => 'This specimen type has clinical history and cannot be deleted. Mark it inactive instead.']);
        }

        LabTest::where('default_specimen_type_id', $specimenType->id)->update(['default_specimen_type_id' => null]);
        $before = $specimenType->toArray();
        $specimenType->delete();
        app(AuditService::class)->record('lab.specimen_type_deleted', $specimenType, $before, null, actor: $request->user());

        return back()->with('success', 'Specimen type deleted.');
    }

    public function updateUnit(Request $request, LabUnit $unit): RedirectResponse
    {
        abort_unless($request->user()->can('lab.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        abort_unless($unit->hospital_id === $this->currentHospital()->id, 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('lab_units')->where('hospital_id', $unit->hospital_id)->ignore($unit->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
        $before = $unit->toArray();
        $unit->update($validated);
        app(AuditService::class)->record('lab.unit_updated', $unit, $before, $unit->fresh()->toArray(), actor: $request->user());

        return back()->with('success', 'Laboratory unit updated.');
    }

    public function destroyUnit(Request $request, LabUnit $unit): RedirectResponse
    {
        abort_unless($request->user()->can('lab.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        abort_unless($unit->hospital_id === $this->currentHospital()->id, 403);

        if (DB::table('lab_results')->where('lab_unit_id', $unit->id)->exists()) {
            return back()->withErrors(['catalogue' => 'This unit has result history and cannot be deleted. Mark it inactive instead.']);
        }

        DB::table('lab_test_components')->where('lab_unit_id', $unit->id)->update(['lab_unit_id' => null]);
        $before = $unit->toArray();
        $unit->delete();
        app(AuditService::class)->record('lab.unit_deleted', $unit, $before, null, actor: $request->user());

        return back()->with('success', 'Laboratory unit deleted.');
    }

    public function updateTest(Request $request, LabTest $test): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->hospital_id === $this->currentHospital()->id, 403);

        $validated = $request->validate([
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $test->hospital_id)],
            'default_specimen_type_id' => ['nullable', Rule::exists('lab_specimen_types', 'id')->where('hospital_id', $test->hospital_id)],
            'billable_service_id' => ['nullable', Rule::exists('billable_services', 'id')->where('hospital_id', $test->hospital_id)],
            'code' => ['required', 'string', 'max:40', Rule::unique('lab_tests')->where('hospital_id', $test->hospital_id)->ignore($test->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'turnaround_time' => ['nullable', 'string', 'max:100'],
            'requires_approval' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
        $before = $test->toArray();
        $test->update($validated);
        app(AuditService::class)->record('lab.test_updated', $test, $before, $test->fresh()->toArray(), actor: $request->user());

        return back()->with('success', 'Laboratory test updated.');
    }

    public function destroyTest(Request $request, LabTest $test): RedirectResponse
    {
        $this->authorize('update', $test);
        abort_unless($test->hospital_id === $this->currentHospital()->id, 403);

        if (DB::table('lab_request_tests')->where('lab_test_id', $test->id)->exists()) {
            return back()->withErrors(['catalogue' => 'This test has clinical request history and cannot be deleted. Mark it inactive instead.']);
        }

        $before = $test->toArray();
        $test->profiles()->detach();
        $test->delete();
        app(AuditService::class)->record('lab.test_deleted', $test, $before, null, actor: $request->user());

        return back()->with('success', 'Laboratory test deleted.');
    }

    public function updateProfile(Request $request, LabTestProfile $profile): RedirectResponse
    {
        abort_unless($request->user()->can('lab.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        abort_unless($profile->hospital_id === $this->currentHospital()->id, 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('lab_test_profiles')->where('hospital_id', $profile->hospital_id)->ignore($profile->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'lab_test_ids' => ['array'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('hospital_id', $profile->hospital_id)],
            'is_active' => ['boolean'],
        ]);
        $testIds = $validated['lab_test_ids'] ?? [];
        unset($validated['lab_test_ids']);
        $before = $profile->load('tests')->toArray();
        $profile->update($validated);
        $profile->tests()->sync($testIds);
        app(AuditService::class)->record('lab.profile_updated', $profile, $before, $profile->fresh('tests')->toArray(), actor: $request->user());

        return back()->with('success', 'Laboratory panel updated.');
    }

    public function destroyProfile(Request $request, LabTestProfile $profile): RedirectResponse
    {
        abort_unless($request->user()->can('lab.catalogue.manage') || $request->user()->hasRole('superadmin'), 403);
        abort_unless($profile->hospital_id === $this->currentHospital()->id, 403);

        $before = $profile->load('tests')->toArray();
        $profile->tests()->detach();
        $profile->delete();
        app(AuditService::class)->record('lab.profile_deleted', $profile, $before, null, actor: $request->user());

        return back()->with('success', 'Laboratory panel deleted.');
    }

    public function requests(Request $request): Response
    {
        $this->authorize('viewAny', LabRequest::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Laboratory/Requests', $this->shared($hospital->id) + [
            'patients' => Patient::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
            'visits' => Visit::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'facility_id', 'department_id', 'status']),
            'encounters' => ClinicalEncounter::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'visit_id', 'status']),
            'requests' => LabRequest::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'tests', 'specimens'])->where('hospital_id', $hospital->id)->when($request->status, fn ($query, $status) => $query->where('status', $status))->latest('ordered_at')->paginate(15)->withQueryString(),
        ]);
    }

    public function storeRequest(Request $request, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('order', LabRequest::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('hospital_id', $hospital->id)],
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospital->id)],
            'visit_id' => ['nullable', Rule::exists('visits', 'id')->where('hospital_id', $hospital->id)],
            'clinical_encounter_id' => ['nullable', Rule::exists('clinical_encounters', 'id')->where('hospital_id', $hospital->id)],
            'lab_test_ids' => ['array'],
            'lab_test_ids.*' => [Rule::exists('lab_tests', 'id')->where('hospital_id', $hospital->id)],
            'lab_test_profile_ids' => ['array'],
            'lab_test_profile_ids.*' => [Rule::exists('lab_test_profiles', 'id')->where('hospital_id', $hospital->id)],
            'priority' => ['required', Rule::in(['routine', 'urgent'])],
            'clinical_notes' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);
        $labRequest = $workflow->order($validated + ['hospital_id' => $hospital->id], $request->user());

        return redirect()->route('admin.lab.requests.show', $labRequest)->with('success', 'Laboratory request ordered.');
    }

    public function show(LabRequest $labRequest): Response
    {
        $this->authorize('view', $labRequest);

        return Inertia::render('Admin/Laboratory/RequestShow', $this->shared($labRequest->hospital_id) + [
            'labRequest' => $labRequest->load(['patient', 'visit', 'encounter', 'tests.test.components.unit', 'tests.test.components.referenceRanges', 'specimens.type', 'results.component.unit', 'amendments', 'invoice.lines']),
        ]);
    }

    public function collect(Request $request, LabRequest $labRequest, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('collect', $labRequest);
        $validated = $request->validate(['lab_specimen_type_id' => ['required', Rule::exists('lab_specimen_types', 'id')->where('hospital_id', $labRequest->hospital_id)]]);
        $type = LabSpecimenType::where('hospital_id', $labRequest->hospital_id)->findOrFail($validated['lab_specimen_type_id']);
        $workflow->collectSpecimen($labRequest, $type, $request->user());

        return back()->with('success', 'Specimen collected.');
    }

    public function specimenTransition(Request $request, LabSpecimen $specimen, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('collect', $specimen->request);
        $validated = $request->validate(['action' => ['required', Rule::in(['receive', 'reject'])], 'reason' => ['nullable', 'required_if:action,reject', 'string', 'max:1000']]);
        $validated['action'] === 'receive' ? $workflow->receiveSpecimen($specimen, $request->user()) : $workflow->rejectSpecimen($specimen, $request->user(), $validated['reason']);

        return back()->with('success', 'Specimen updated.');
    }

    public function result(Request $request, LabRequestTest $requestTest, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('result', $requestTest->request);
        $validated = $request->validate(['lab_test_component_id' => ['required', Rule::exists('lab_test_components', 'id')->where('lab_test_id', $requestTest->lab_test_id)], 'numeric_value' => ['nullable', 'numeric'], 'text_value' => ['nullable', 'string', 'max:5000'], 'qualitative_value' => ['nullable', 'string', 'max:255'], 'comment' => ['nullable', 'string', 'max:5000']]);
        $component = LabTestComponent::where('lab_test_id', $requestTest->lab_test_id)->findOrFail($validated['lab_test_component_id']);
        $workflow->enterResult($requestTest, $component, $validated, $request->user());

        return back()->with('success', 'Result drafted.');
    }

    public function resultTransition(Request $request, LabResult $result, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $action = $request->input('action');
        $this->authorize($action === 'approve' ? 'approve' : 'verify', $result->request);
        $validated = $request->validate(['action' => ['required', Rule::in(['verify', 'approve'])]]);
        $validated['action'] === 'verify' ? $workflow->verifyResult($result, $request->user()) : $workflow->approveResult($result, $request->user());

        return back()->with('success', 'Result updated.');
    }

    public function acknowledgeCritical(Request $request, LabResult $result, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('approve', $result->request);
        $validated = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $workflow->acknowledgeCritical($result, $request->user(), $validated['notes']);

        return back()->with('success', 'Critical result acknowledged.');
    }

    public function release(Request $request, LabRequest $labRequest, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('approve', $labRequest);
        $workflow->releaseRequest($labRequest, $request->user());

        return back()->with('success', 'Report released.');
    }

    public function amend(Request $request, LabRequest $labRequest, LaboratoryWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('amend', $labRequest);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'content' => ['required', 'string', 'max:10000']]);
        $workflow->amendReport($labRequest, $validated, $request->user());

        return back()->with('success', 'Report amendment added.');
    }

    public function report(LabRequest $labRequest): Response
    {
        $this->authorize('view', $labRequest);
        abort_unless(in_array($labRequest->status, ['approved', 'released'], true), 404);

        return Inertia::render('Admin/Laboratory/Report', [
            'labRequest' => $labRequest->load(['patient', 'tests', 'results.component.unit', 'amendments']),
        ]);
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'billableServices' => BillableService::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'labTests' => LabTest::with('components.unit')->where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'labProfiles' => LabTestProfile::with('tests:id,code,name')->where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'specimenTypes' => LabSpecimenType::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'units' => LabUnit::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
