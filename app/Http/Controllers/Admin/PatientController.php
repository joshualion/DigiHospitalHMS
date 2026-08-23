<?php

namespace App\Http\Controllers\Admin;

use App\Models\Facility;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use App\Services\PatientActivity;
use App\Services\PatientDuplicateDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Patient::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Patients/Index', [
            'filters' => $request->only(['search', 'status']),
            'facilities' => Facility::where('hospital_id', $hospital->id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'patients' => Patient::with('registrationFacility:id,name,code')
                ->forHospital($hospital->id)
                ->search($request->search)
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, NumberSequenceService $numbers, PatientDuplicateDetector $duplicates, AuditService $audit, PatientActivity $activity): RedirectResponse
    {
        $this->authorize('create', Patient::class);
        $hospital = $this->currentHospital();
        $this->normalizeOptionalRepeaters($request);
        $validated = $request->validate($this->patientRules($hospital->id));

        $warnings = $duplicates->warnings($hospital->id, $validated);
        if ($warnings !== [] && ! $request->boolean('acknowledge_duplicates')) {
            return back()->withInput()->with('duplicate_warnings', $warnings);
        }

        $patient = DB::transaction(function () use ($validated, $hospital, $request, $numbers, $audit, $activity): Patient {
            $sequence = NumberSequence::where('hospital_id', $hospital->id)
                ->whereNull('facility_id')
                ->where('key', 'patient_number')
                ->where('status', 'active')
                ->firstOrFail();

            $patient = new Patient([
                'hospital_id' => $hospital->id,
                'registration_facility_id' => $validated['registration_facility_id'],
                'registered_by' => $request->user()->id,
                'hospital_number' => $numbers->allocate($sequence),
                'status' => 'active',
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'estimated_age_years' => $validated['estimated_age_years'] ?? null,
                'is_dob_estimated' => (bool) ($validated['is_dob_estimated'] ?? false),
                'sex' => $validated['sex'],
                'marital_status' => $validated['marital_status'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);
            $patient->phone = $validated['phone'] ?? null;
            $patient->email = $validated['email'] ?? null;
            $patient->save();

            $this->createIdentifiers($patient, $validated['identifiers'] ?? [], $request->user()->id);
            $this->createContacts($patient, $validated['contacts'] ?? [], $request->user()->id);

            $audit->record('patients.registered', $patient, null, $patient->fresh()->toArray());
            $activity->record($patient, 'registered', $request->user(), ['facility_id' => $patient->registration_facility_id]);

            return $patient;
        });

        return redirect()->route('admin.patients.show', $patient)->with('success', 'Patient registered.');
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('view', $patient);

        return Inertia::render('Admin/Patients/Show', [
            'patient' => $this->patientPayload($patient),
            'facilities' => Facility::where('hospital_id', $patient->hospital_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function update(Request $request, Patient $patient, PatientDuplicateDetector $duplicates, AuditService $audit, PatientActivity $activity): RedirectResponse
    {
        $this->authorize('update', $patient);
        $this->normalizeOptionalRepeaters($request);
        $validated = $request->validate($this->patientRules($patient->hospital_id, updating: true));

        $warnings = $duplicates->warnings($patient->hospital_id, $validated, $patient);
        if ($warnings !== [] && ! $request->boolean('acknowledge_duplicates')) {
            return back()->withInput()->with('duplicate_warnings', $warnings);
        }

        DB::transaction(function () use ($patient, $validated, $request, $audit, $activity): void {
            $before = $patient->fresh()->toArray();

            $patient->fill([
                'registration_facility_id' => $validated['registration_facility_id'],
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'estimated_age_years' => $validated['estimated_age_years'] ?? null,
                'is_dob_estimated' => (bool) ($validated['is_dob_estimated'] ?? false),
                'sex' => $validated['sex'],
                'marital_status' => $validated['marital_status'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'address' => $validated['address'] ?? null,
            ]);
            $patient->phone = $validated['phone'] ?? null;
            $patient->email = $validated['email'] ?? null;
            $patient->save();

            $this->createIdentifiers($patient, $validated['identifiers'] ?? [], $request->user()->id, skipExisting: true);
            $this->createContacts($patient, $validated['contacts'] ?? [], $request->user()->id);

            $audit->record('patients.updated', $patient, $before, $patient->fresh()->toArray());
            $activity->record($patient, 'updated', $request->user());
        });

        return back()->with('success', 'Patient profile updated.');
    }

    public function status(Request $request, Patient $patient, AuditService $audit, PatientActivity $activity): RedirectResponse
    {
        $this->authorize('changeStatus', $patient);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'archived', 'deceased'])],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $before = $patient->toArray();
        $updates = [
            'status' => $validated['status'],
            'status_reason' => $validated['reason'],
        ];

        if ($validated['status'] === 'archived') {
            $updates += ['archived_at' => now(), 'archived_by' => $request->user()->id, 'deceased_at' => null, 'deceased_by' => null];
        } elseif ($validated['status'] === 'deceased') {
            $updates += ['deceased_at' => now(), 'deceased_by' => $request->user()->id];
        } else {
            $updates += ['archived_at' => null, 'archived_by' => null, 'deceased_at' => null, 'deceased_by' => null];
        }

        $patient->forceFill($updates)->save();
        $audit->record("patients.status.{$validated['status']}", $patient, $before, $patient->fresh()->toArray(), reason: $validated['reason']);
        $activity->record($patient, "status_{$validated['status']}", $request->user(), ['reason' => $validated['reason']]);

        return back()->with('success', 'Patient status updated.');
    }

    public function storeAllergy(Request $request, Patient $patient, AuditService $audit, PatientActivity $activity): RedirectResponse
    {
        $this->authorize('recordClinicalIdentity', $patient);
        $validated = $request->validate([
            'substance' => ['required', 'string', 'max:255'],
            'reaction' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(['unknown', 'mild', 'moderate', 'severe'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'entered-in-error'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $allergy = $patient->allergies()->create($validated + [
            'hospital_id' => $patient->hospital_id,
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        $audit->record('patients.allergy_recorded', $allergy, null, $allergy->toArray(), actor: $request->user());
        $activity->record($patient, 'allergy_recorded', $request->user(), ['allergy_id' => $allergy->id]);

        return back()->with('success', 'Allergy recorded.');
    }

    public function storeAlert(Request $request, Patient $patient, AuditService $audit, PatientActivity $activity): RedirectResponse
    {
        $this->authorize('recordClinicalIdentity', $patient);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:80'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'resolved'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $alert = $patient->alerts()->create($validated + [
            'hospital_id' => $patient->hospital_id,
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        $audit->record('patients.alert_recorded', $alert, null, $alert->toArray(), actor: $request->user());
        $activity->record($patient, 'alert_recorded', $request->user(), ['alert_id' => $alert->id]);

        return back()->with('success', 'Alert recorded.');
    }

    private function patientRules(int $hospitalId, bool $updating = false): array
    {
        return [
            'registration_facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospitalId)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today', 'required_without:estimated_age_years'],
            'estimated_age_years' => ['nullable', 'integer', 'min:0', 'max:130', 'required_without:date_of_birth'],
            'is_dob_estimated' => ['boolean'],
            'sex' => ['required', Rule::in(['female', 'male', 'intersex', 'unknown'])],
            'marital_status' => ['nullable', 'string', 'max:40'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'identifiers' => ['array'],
            'identifiers.*.type' => ['required_with:identifiers.*.value', 'string', 'max:80'],
            'identifiers.*.value' => ['required_with:identifiers.*.type', 'string', 'max:255'],
            'identifiers.*.is_searchable' => ['boolean'],
            'contacts' => ['array'],
            'contacts.*.type' => ['required_with:contacts.*.name', Rule::in(['contact', 'next_of_kin'])],
            'contacts.*.name' => ['required_with:contacts.*.type', 'string', 'max:255'],
            'contacts.*.relationship' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.address' => ['nullable', 'string', 'max:1000'],
            'contacts.*.is_next_of_kin' => ['boolean'],
            'contacts.*.is_primary' => ['boolean'],
            'acknowledge_duplicates' => ['boolean'],
        ];
    }

    private function normalizeOptionalRepeaters(Request $request): void
    {
        $request->merge([
            'identifiers' => collect($request->input('identifiers', []))
                ->filter(fn (array $identifier): bool => filled($identifier['value'] ?? null))
                ->values()
                ->all(),
            'contacts' => collect($request->input('contacts', []))
                ->filter(fn (array $contact): bool => filled($contact['name'] ?? null) || filled($contact['phone'] ?? null) || filled($contact['email'] ?? null))
                ->values()
                ->all(),
        ]);
    }

    private function createIdentifiers(Patient $patient, array $identifiers, int $actorId, bool $skipExisting = false): void
    {
        foreach ($identifiers as $identifier) {
            if (! filled($identifier['type'] ?? null) || ! filled($identifier['value'] ?? null)) {
                continue;
            }

            $model = $patient->identifiers()->make([
                'hospital_id' => $patient->hospital_id,
                'type' => $identifier['type'],
                'is_searchable' => (bool) ($identifier['is_searchable'] ?? true),
                'recorded_by' => $actorId,
            ]);
            $model->value = $identifier['value'];

            if ($skipExisting && $patient->identifiers()->where('type', $model->type)->where('value_hash', $model->value_hash)->exists()) {
                continue;
            }

            $model->save();
        }
    }

    private function createContacts(Patient $patient, array $contacts, int $actorId): void
    {
        foreach ($contacts as $contact) {
            if (! filled($contact['name'] ?? null)) {
                continue;
            }

            $model = $patient->contacts()->make([
                'hospital_id' => $patient->hospital_id,
                'type' => $contact['type'] ?? 'contact',
                'name' => $contact['name'],
                'relationship' => $contact['relationship'] ?? null,
                'address' => $contact['address'] ?? null,
                'is_next_of_kin' => (bool) ($contact['is_next_of_kin'] ?? false),
                'is_primary' => (bool) ($contact['is_primary'] ?? false),
                'recorded_by' => $actorId,
            ]);
            $model->phone = $contact['phone'] ?? null;
            $model->email = $contact['email'] ?? null;
            $model->save();
        }
    }

    private function patientPayload(Patient $patient): array
    {
        return $patient->load([
            'registrationFacility:id,name,code',
            'identifiers.recorder:id,firstname,lastname',
            'contacts.recorder:id,firstname,lastname',
            'allergies.recorder:id,firstname,lastname',
            'alerts.recorder:id,firstname,lastname',
            'activityEvents.actor:id,firstname,lastname',
        ])->toArray();
    }
}
