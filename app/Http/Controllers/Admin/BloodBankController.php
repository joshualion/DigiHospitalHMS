<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admission;
use App\Models\BloodBankAmendment;
use App\Models\BloodBankLocation;
use App\Models\BloodCompatibilityTest;
use App\Models\BloodComponent;
use App\Models\BloodComponentIssue;
use App\Models\BloodComponentReservation;
use App\Models\BloodComponentType;
use App\Models\BloodDonation;
use App\Models\BloodDonor;
use App\Models\BloodDonorCategory;
use App\Models\BloodGroupResult;
use App\Models\BloodRequest;
use App\Models\BloodScreeningResult;
use App\Models\BloodScreeningTest;
use App\Models\BloodStorageUnit;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\PatientBloodGroup;
use App\Models\StaffProfile;
use App\Services\AuditService;
use App\Services\BloodBankWorkflowService;
use App\Services\BloodRequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BloodBankController extends FoundationController
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('blood-bank.view') || $request->user()->can('blood-bank.requests.view') || $request->user()->hasRole('superadmin'), 403);
        $hospital = $this->currentHospital();
        $canViewInventory = $request->user()->can('blood-bank.view') || $request->user()->hasRole('superadmin');

        return Inertia::render('Admin/BloodBank/Index', $this->shared($hospital->id) + [
            'canViewInventory' => $canViewInventory,
            'donors' => $canViewInventory ? BloodDonor::with(['category', 'screenings', 'deferrals'])->where('hospital_id', $hospital->id)->search($request->search)->latest()->paginate(12)->withQueryString() : ['data' => []],
            'donations' => $canViewInventory ? BloodDonation::with(['donor', 'groupResult', 'screeningResults.test', 'components.type'])->where('hospital_id', $hospital->id)->latest('collected_at')->limit(20)->get() : [],
            'components' => $canViewInventory ? BloodComponent::with(['type', 'donation.donor', 'location', 'storageUnit'])->where('hospital_id', $hospital->id)->latest()->limit(30)->get() : [],
            'requests' => BloodRequest::with(['patient', 'componentType', 'clinician.user', 'specimens', 'reservations.component', 'issues.component'])->where('hospital_id', $hospital->id)->latest()->limit(30)->get(),
            'patients' => Patient::where('hospital_id', $hospital->id)->where('status', 'active')->latest()->limit(50)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
            'encounters' => ClinicalEncounter::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'status']),
            'admissions' => Admission::where('hospital_id', $hospital->id)->latest()->limit(50)->get(['id', 'patient_id', 'admission_number', 'status']),
            'clinicians' => StaffProfile::with('user:id,firstname,lastname')->where('hospital_id', $hospital->id)->where('is_active', true)->get(['id', 'user_id', 'job_title']),
            'reports' => $canViewInventory ? [
                'quarantine' => BloodComponent::where('hospital_id', $hospital->id)->where('state', 'quarantined')->count(),
                'available' => BloodComponent::where('hospital_id', $hospital->id)->where('state', 'available')->count(),
                'near_expiry' => BloodComponent::where('hospital_id', $hospital->id)->whereIn('state', ['available', 'quarantined', 'transferred'])->whereBetween('expires_on', [today(), today()->addDays(7)])->count(),
                'expired' => BloodComponent::where('hospital_id', $hospital->id)->whereDate('expires_on', '<', today())->count(),
                'stock_by_group' => BloodComponent::query()->selectRaw('abo_group, rh_factor, blood_component_type_id, state, count(*) as total')->where('hospital_id', $hospital->id)->groupBy('abo_group', 'rh_factor', 'blood_component_type_id', 'state')->get(),
            ] : ['quarantine' => 0, 'available' => 0, 'near_expiry' => 0, 'expired' => 0, 'stock_by_group' => []],
        ]);
    }

    public function showDonor(BloodDonor $donor): Response
    {
        $this->authorize('view', $donor);

        return Inertia::render('Admin/BloodBank/DonorShow', $this->shared($donor->hospital_id) + [
            'donor' => $donor->load(['category', 'screenings', 'deferrals', 'donations.groupResult', 'donations.screeningResults.test', 'donations.components.type']),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $this->authorize('create', BloodBankLocation::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:40', Rule::unique('blood_bank_locations')->where('hospital_id', $hospital->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $location = BloodBankLocation::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('blood_bank.location_created', $location, null, $location->toArray(), actor: $request->user());

        return back()->with('success', 'Blood-bank location created.');
    }

    public function storeStorageUnit(Request $request): RedirectResponse
    {
        $this->authorize('create', BloodBankLocation::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'blood_bank_location_id' => ['required', Rule::exists('blood_bank_locations', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'storage_type' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $unit = BloodStorageUnit::create($validated + ['hospital_id' => $hospital->id, 'status' => 'active']);
        app(AuditService::class)->record('blood_bank.storage_unit_created', $unit, null, $unit->toArray(), actor: $request->user());

        return back()->with('success', 'Storage unit created.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorize('create', BloodBankLocation::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['code' => ['required', 'string', 'max:40', Rule::unique('blood_donor_categories')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000']]);
        $category = BloodDonorCategory::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('blood_bank.donor_category_created', $category, null, $category->toArray(), actor: $request->user());

        return back()->with('success', 'Donor category created.');
    }

    public function storeComponentType(Request $request): RedirectResponse
    {
        $this->authorize('create', BloodBankLocation::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['code' => ['required', 'string', 'max:40', Rule::unique('blood_component_types')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'default_shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:3650'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $type = BloodComponentType::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('blood_bank.component_type_created', $type, null, $type->toArray(), actor: $request->user());

        return back()->with('success', 'Component type created.');
    }

    public function storeScreeningTest(Request $request): RedirectResponse
    {
        $this->authorize('create', BloodBankLocation::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'lab_test_id' => ['nullable', Rule::exists('lab_tests', 'id')->where('hospital_id', $hospital->id)],
            'code' => ['required', 'string', 'max:40', Rule::unique('blood_screening_tests')->where('hospital_id', $hospital->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_required_for_release' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $test = BloodScreeningTest::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('blood_bank.screening_test_created', $test, null, $test->toArray(), actor: $request->user());

        return back()->with('success', 'Screening test created.');
    }

    public function storeDonor(Request $request, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('create', BloodDonor::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'blood_donor_category_id' => ['nullable', Rule::exists('blood_donor_categories', 'id')->where('hospital_id', $hospital->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'sex' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'identifier_type' => ['nullable', 'string', 'max:80'],
            'identifier_value' => ['nullable', 'string', 'max:255'],
            'consented_at' => ['nullable', 'date'],
            'consent_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $donor = $workflow->registerDonor($validated + ['hospital_id' => $hospital->id], $request->user());

        return redirect()->route('admin.blood-bank.donors.show', $donor)->with('success', 'Donor registered.');
    }

    public function screeningDecision(Request $request, BloodDonor $donor, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('screen', $donor);
        $validated = $request->validate([
            'eligibility_status' => ['required', Rule::in(['eligible', 'deferred', 'ineligible'])],
            'decision_reason' => ['required', 'string', 'max:2000'],
            'deferred_until' => ['nullable', 'date'],
            'responses' => ['array'],
        ]);
        $workflow->recordScreeningDecision($donor, $validated, $request->user());

        return back()->with('success', 'Eligibility decision recorded.');
    }

    public function scheduleAppointment(Request $request, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('create', BloodDonor::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'blood_donor_id' => ['required', Rule::exists('blood_donors', 'id')->where('hospital_id', $hospital->id)],
            'blood_bank_location_id' => ['nullable', Rule::exists('blood_bank_locations', 'id')->where('hospital_id', $hospital->id)],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->scheduleAppointment($validated + ['hospital_id' => $hospital->id], $request->user());

        return back()->with('success', 'Donation appointment scheduled.');
    }

    public function collect(Request $request, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('collect', BloodDonation::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'blood_donor_id' => ['required', Rule::exists('blood_donors', 'id')->where('hospital_id', $hospital->id)],
            'blood_donation_appointment_id' => ['nullable', Rule::exists('blood_donation_appointments', 'id')->where('hospital_id', $hospital->id)],
            'blood_bank_location_id' => ['required', Rule::exists('blood_bank_locations', 'id')->where('hospital_id', $hospital->id)],
            'collected_at' => ['nullable', 'date'],
            'bag_type' => ['required', 'string', 'max:120'],
            'volume_ml' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $donation = $workflow->collect($validated + ['hospital_id' => $hospital->id], $request->user());

        return redirect()->route('admin.blood-bank.donations.show', $donation)->with('success', 'Collection recorded and quarantined.');
    }

    public function showDonation(BloodDonation $donation): Response
    {
        $this->authorize('view', $donation);

        return Inertia::render('Admin/BloodBank/DonationShow', $this->shared($donation->hospital_id) + [
            'donation' => $donation->load(['donor.category', 'groupResult', 'screeningResults.test', 'components.type', 'components.location', 'components.storageUnit']),
            'events' => $donation->components()->with('transfers')->get(),
            'amendments' => BloodBankAmendment::where('hospital_id', $donation->hospital_id)->where(function ($query) use ($donation): void {
                $query->where(fn ($inner) => $inner->where('subject_type', BloodDonation::class)->where('subject_id', $donation->id))
                    ->orWhereIn('subject_id', $donation->components()->pluck('id'))->where('subject_type', BloodComponent::class);
            })->latest('authored_at')->get(),
        ]);
    }

    public function showRequest(BloodRequest $bloodRequest): Response
    {
        $this->authorize('view', $bloodRequest);

        return Inertia::render('Admin/BloodBank/RequestShow', $this->shared($bloodRequest->hospital_id) + [
            'bloodRequest' => $bloodRequest->load(['patient', 'encounter', 'admission', 'clinician.user', 'componentType', 'specimens', 'compatibilityTests.component.type', 'reservations.component.type', 'issues.component.type']),
            'availableComponents' => BloodComponent::with(['type', 'location', 'storageUnit'])->where('hospital_id', $bloodRequest->hospital_id)->where('blood_component_type_id', $bloodRequest->blood_component_type_id)->where('state', 'available')->where(function ($query): void {
                $query->whereNull('expires_on')->orWhereDate('expires_on', '>=', today());
            })->latest()->get(),
            'patientGroups' => PatientBloodGroup::where('hospital_id', $bloodRequest->hospital_id)->where('patient_id', $bloodRequest->patient_id)->latest()->get(),
        ]);
    }

    public function storeRequest(Request $request, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('order', BloodRequest::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospital->id)],
            'clinical_encounter_id' => ['nullable', Rule::exists('clinical_encounters', 'id')->where('hospital_id', $hospital->id)],
            'admission_id' => ['nullable', Rule::exists('admissions', 'id')->where('hospital_id', $hospital->id)],
            'requesting_clinician_id' => ['required', Rule::exists('staff_profiles', 'id')->where('hospital_id', $hospital->id)],
            'blood_component_type_id' => ['required', Rule::exists('blood_component_types', 'id')->where('hospital_id', $hospital->id)],
            'quantity_requested' => ['required', 'integer', 'min:1', 'max:20'],
            'clinical_indication' => ['required', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(['routine', 'urgent', 'emergency'])],
            'required_at' => ['nullable', 'date'],
        ]);
        $bloodRequest = $workflow->create($validated + ['hospital_id' => $hospital->id], $request->user());

        return redirect()->route('admin.blood-bank.requests.show', $bloodRequest)->with('success', 'Blood request drafted.');
    }

    public function requestAction(Request $request, BloodRequest $bloodRequest, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('manage', $bloodRequest);
        $validated = $request->validate(['state' => ['required', Rule::in(['submitted', 'accepted', 'cancelled', 'rejected'])], 'reason' => ['required', 'string', 'max:2000']]);
        $workflow->transition($bloodRequest, $validated['state'], $request->user(), $validated['reason']);

        return back()->with('success', 'Blood request updated.');
    }

    public function collectPatientSpecimen(Request $request, BloodRequest $bloodRequest, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('collect', $bloodRequest);
        $validated = $request->validate([
            'collected_at' => ['nullable', 'date'],
            'collection_location' => ['nullable', 'string', 'max:120'],
            'patient_confirmed_name' => ['required', 'string', 'max:255'],
            'patient_confirmed_identifier' => ['required', 'string', 'max:255'],
            'label_status' => ['required', Rule::in(['matched', 'discrepant'])],
            'label_discrepancy_notes' => ['nullable', 'required_if:label_status,discrepant', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->collectSpecimen($bloodRequest, $validated, $request->user());

        return back()->with('success', 'Patient specimen collected.');
    }

    public function enterPatientGroup(Request $request, BloodRequest $bloodRequest, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('test', $bloodRequest);
        $validated = $request->validate(['blood_request_specimen_id' => ['nullable', Rule::exists('blood_request_specimens', 'id')->where('hospital_id', $bloodRequest->hospital_id)], 'abo_group' => ['required', Rule::in(['A', 'B', 'AB', 'O'])], 'rh_factor' => ['required', Rule::in(['positive', 'negative'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $workflow->enterPatientGroup($bloodRequest, $validated, $request->user());

        return back()->with('success', 'Patient blood group drafted.');
    }

    public function verifyPatientGroup(Request $request, PatientBloodGroup $group, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        abort_unless($group->hospital_id === $this->currentHospital()->id, 403);
        $bloodRequest = BloodRequest::where('hospital_id', $group->hospital_id)->whereHas('specimens', fn ($query) => $query->whereKey($group->blood_request_specimen_id))->firstOrFail();
        $this->authorize('authorizeTest', $bloodRequest);
        $workflow->verifyPatientGroup($group, $request->user());

        return back()->with('success', 'Patient blood group verified.');
    }

    public function enterCompatibility(Request $request, BloodRequest $bloodRequest, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('test', $bloodRequest);
        $validated = $request->validate(['blood_request_specimen_id' => ['nullable', Rule::exists('blood_request_specimens', 'id')->where('hospital_id', $bloodRequest->hospital_id)], 'blood_component_id' => ['nullable', Rule::exists('blood_components', 'id')->where('hospital_id', $bloodRequest->hospital_id)], 'test_type' => ['nullable', 'string', 'max:120'], 'result' => ['required', 'string', 'max:120'], 'interpretation' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $workflow->enterCompatibility($bloodRequest, $validated, $request->user());

        return back()->with('success', 'Manual compatibility result drafted.');
    }

    public function authorizeCompatibility(Request $request, BloodCompatibilityTest $test, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $bloodRequest = $test->request;
        $this->authorize('authorizeTest', $bloodRequest);
        $workflow->authorizeCompatibility($test, $request->user());

        return back()->with('success', 'Compatibility result authorized.');
    }

    public function reserveComponent(Request $request, BloodRequest $bloodRequest, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('reserve', $bloodRequest);
        $validated = $request->validate(['blood_component_id' => ['required', Rule::exists('blood_components', 'id')->where('hospital_id', $bloodRequest->hospital_id)], 'expiry_minutes' => ['nullable', 'integer', 'min:1', 'max:10080']]);
        $workflow->reserve($bloodRequest, BloodComponent::findOrFail($validated['blood_component_id']), $request->user(), $validated['expiry_minutes'] ?? null);

        return back()->with('success', 'Component reserved.');
    }

    public function issueComponent(Request $request, BloodRequest $bloodRequest, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('issue', $bloodRequest);
        $validated = $request->validate(['blood_component_reservation_id' => ['required', Rule::exists('blood_component_reservations', 'id')->where('hospital_id', $bloodRequest->hospital_id)], 'received_by_name' => ['required', 'string', 'max:255'], 'receiver_role' => ['nullable', 'string', 'max:120'], 'issued_at' => ['nullable', 'date'], 'destination' => ['required', 'string', 'max:255']]);
        $workflow->issue($bloodRequest, BloodComponentReservation::findOrFail($validated['blood_component_reservation_id']), $validated, $request->user());

        return back()->with('success', 'Component issued.');
    }

    public function returnIssue(Request $request, BloodComponentIssue $issue, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $bloodRequest = $issue->request;
        $this->authorize('issue', $bloodRequest);
        $validated = $request->validate(['return_reason' => ['required', 'string', 'max:2000'], 'return_assessment' => ['required', 'string', 'max:2000']]);
        $workflow->returnToStock($issue, $validated, $request->user());

        return back()->with('success', 'Component return assessed and recorded.');
    }

    public function reverseIssue(Request $request, BloodComponentIssue $issue, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $bloodRequest = $issue->request;
        $this->authorize('issue', $bloodRequest);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $workflow->reverseIssue($issue, $validated['reason'], $request->user());

        return back()->with('success', 'Issue reversed without deleting history.');
    }

    public function emergencyRelease(Request $request, BloodRequest $bloodRequest, BloodRequestWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('emergencyRelease', $bloodRequest);
        $validated = $request->validate(['justification' => ['required', 'string', 'max:2000']]);
        $workflow->authorizeEmergencyRelease($bloodRequest, $validated['justification'], $request->user());

        return back()->with('success', 'Emergency release authorization recorded.');
    }

    public function issueDocument(BloodComponentIssue $issue): Response
    {
        $this->authorize('view', $issue->request);

        return Inertia::render('Admin/BloodBank/IssueDocument', [
            'issue' => $issue->load(['request.patient', 'request.componentType', 'component.type']),
        ]);
    }

    public function enterGroup(Request $request, BloodDonation $donation, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('test', $donation);
        $validated = $request->validate(['abo_group' => ['required', Rule::in(['A', 'B', 'AB', 'O'])], 'rh_factor' => ['required', Rule::in(['positive', 'negative'])], 'notes' => ['nullable', 'string', 'max:2000']]);
        $workflow->enterGroup($donation, $validated, $request->user());

        return back()->with('success', 'Blood group result drafted.');
    }

    public function verifyGroup(Request $request, BloodGroupResult $result, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('verify', $result->donation);
        $workflow->verifyGroup($result, $request->user());

        return back()->with('success', 'Blood group result verified.');
    }

    public function screeningResult(Request $request, BloodDonation $donation, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('test', $donation);
        $validated = $request->validate([
            'blood_screening_test_id' => ['required', Rule::exists('blood_screening_tests', 'id')->where('hospital_id', $donation->hospital_id)],
            'result_value' => ['nullable', 'string', 'max:255'],
            'release_cleared' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $test = BloodScreeningTest::where('hospital_id', $donation->hospital_id)->findOrFail($validated['blood_screening_test_id']);
        $workflow->recordScreeningResult($donation, $test, $validated, $request->user());

        return back()->with('success', 'Screening result drafted.');
    }

    public function verifyScreening(Request $request, BloodScreeningResult $result, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('verify', $result->donation);
        $workflow->verifyScreeningResult($result, $request->user());

        return back()->with('success', 'Screening result verified.');
    }

    public function prepareComponent(Request $request, BloodDonation $donation, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('process', $donation);
        $validated = $request->validate([
            'blood_component_type_id' => ['required', Rule::exists('blood_component_types', 'id')->where('hospital_id', $donation->hospital_id)],
            'blood_bank_location_id' => ['required', Rule::exists('blood_bank_locations', 'id')->where('hospital_id', $donation->hospital_id)],
            'blood_storage_unit_id' => ['nullable', Rule::exists('blood_storage_units', 'id')->where('hospital_id', $donation->hospital_id)],
            'volume_ml' => ['nullable', 'integer', 'min:1'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->prepareComponent($donation, BloodComponentType::findOrFail($validated['blood_component_type_id']), BloodBankLocation::findOrFail($validated['blood_bank_location_id']), isset($validated['blood_storage_unit_id']) ? BloodStorageUnit::findOrFail($validated['blood_storage_unit_id']) : null, $validated, $request->user());

        return back()->with('success', 'Component prepared in quarantine.');
    }

    public function componentAction(Request $request, BloodComponent $component, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $action = $request->input('action');
        $this->authorize($action === 'release' ? 'release' : 'manage', $component);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['release', 'transfer', 'recall', 'discard'])],
            'reason' => ['required', 'string', 'max:2000'],
            'to_location_id' => ['nullable', 'required_if:action,transfer', Rule::exists('blood_bank_locations', 'id')->where('hospital_id', $component->hospital_id)],
            'to_storage_unit_id' => ['nullable', Rule::exists('blood_storage_units', 'id')->where('hospital_id', $component->hospital_id)],
        ]);

        match ($validated['action']) {
            'release' => $workflow->releaseComponent($component, $request->user(), $validated['reason']),
            'transfer' => $workflow->transferComponent($component, BloodBankLocation::findOrFail($validated['to_location_id']), isset($validated['to_storage_unit_id']) ? BloodStorageUnit::findOrFail($validated['to_storage_unit_id']) : null, $request->user(), $validated['reason']),
            'recall' => $workflow->recallComponent($component, $request->user(), $validated['reason']),
            'discard' => $workflow->discardComponent($component, $request->user(), $validated['reason']),
        };

        return back()->with('success', 'Component updated.');
    }

    public function amend(Request $request, BloodComponent $component, BloodBankWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('amend', $component);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'content' => ['required', 'string', 'max:10000']]);
        $workflow->amend($component, $validated['reason'], $validated['content'], $request->user());

        return back()->with('success', 'Blood-bank amendment added.');
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => BloodBankLocation::with('storageUnits')->where('hospital_id', $hospitalId)->orderBy('name')->get(),
            'storageUnits' => BloodStorageUnit::where('hospital_id', $hospitalId)->orderBy('name')->get(),
            'categories' => BloodDonorCategory::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'componentTypes' => BloodComponentType::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'screeningTests' => BloodScreeningTest::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'labTests' => LabTest::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }
}
