<?php

namespace App\Http\Controllers\Admin;

use App\Models\ClinicalEncounter;
use App\Models\EncounterVital;
use App\Models\Patient;
use App\Models\Visit;
use App\Services\ClinicalEncounterWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalEncounterController extends FoundationController
{
    public function worklist(Request $request): Response
    {
        $this->authorize('viewAny', Visit::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Clinical/Worklist', [
            'visits' => Visit::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'queueEntry:id,visit_id,queue_number,status,priority', 'encounter:id,visit_id,status', 'clinician.user:id,firstname,lastname', 'facility:id,name', 'department:id,name'])
                ->where('hospital_id', $hospital->id)
                ->whereIn('status', ['checked_in', 'in_encounter'])
                ->latest('checked_in_at')
                ->paginate(15)
                ->withQueryString(),
            'encounters' => ClinicalEncounter::with(['patient:id,hospital_number,first_name,middle_name,last_name', 'clinician.user:id,firstname,lastname'])
                ->where('hospital_id', $hospital->id)
                ->whereIn('status', ['in_progress', 'paused'])
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function start(Visit $visit, ClinicalEncounterWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('startEncounter', $visit);
        abort_unless($visit->hospital_id === $this->currentHospital()->id, 403);

        $encounter = $workflow->start($visit, request()->user());

        return redirect()->route('admin.encounters.show', $encounter)->with('success', 'Encounter started.');
    }

    public function show(ClinicalEncounter $encounter): Response
    {
        $this->authorize('view', $encounter);

        return Inertia::render('Admin/Clinical/Encounter', [
            'encounter' => $this->payload($encounter),
            'timeline' => $this->timeline($encounter->patient),
        ]);
    }

    public function vitals(Request $request, ClinicalEncounter $encounter, ClinicalEncounterWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('recordVitals', $encounter);
        $validated = $request->validate([
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'temperature_unit' => ['required', Rule::in(['C', 'F'])],
            'pulse' => ['nullable', 'integer', 'min:0', 'max:300'],
            'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'blood_pressure_systolic' => ['nullable', 'integer', 'min:0', 'max:300'],
            'blood_pressure_diastolic' => ['nullable', 'integer', 'min:0', 'max:200'],
            'oxygen_saturation' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weight_kg' => ['nullable', 'numeric', 'between:0,500'],
            'height_cm' => ['nullable', 'numeric', 'between:0,250'],
            'pain_score' => ['nullable', 'integer', 'min:0', 'max:10'],
            'measured_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $workflow->recordVitals($encounter, $validated, $request->user());

        return back()->with('success', 'Vitals recorded.');
    }

    public function assessment(Request $request, ClinicalEncounter $encounter, ClinicalEncounterWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('manage', $encounter);
        $validated = $request->validate([
            'presenting_complaint' => ['nullable', 'string', 'max:5000'],
            'history_presenting_complaint' => ['nullable', 'string', 'max:10000'],
            'medical_history' => ['nullable', 'string', 'max:10000'],
            'surgical_history' => ['nullable', 'string', 'max:10000'],
            'medication_history' => ['nullable', 'string', 'max:10000'],
            'family_history' => ['nullable', 'string', 'max:10000'],
            'social_history' => ['nullable', 'string', 'max:10000'],
            'examination_findings' => ['nullable', 'string', 'max:10000'],
            'treatment_plan' => ['nullable', 'string', 'max:10000'],
            'follow_up_instructions' => ['nullable', 'string', 'max:10000'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:today'],
            'referral_recommendation' => ['nullable', 'string', 'max:5000'],
        ]);
        $workflow->updateAssessment($encounter, $validated, $request->user());

        return back()->with('success', 'Assessment saved.');
    }

    public function diagnosis(Request $request, ClinicalEncounter $encounter, ClinicalEncounterWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('manage', $encounter);
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'coding_system' => ['nullable', 'string', 'max:80'],
            'code' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(['provisional', 'confirmed'])],
        ]);
        $workflow->addDiagnosis($encounter, $validated, $request->user());

        return back()->with('success', 'Diagnosis recorded.');
    }

    public function transition(Request $request, ClinicalEncounter $encounter, ClinicalEncounterWorkflowService $workflow): RedirectResponse
    {
        $action = $request->input('action');
        $this->authorize($action === 'sign' ? 'sign' : 'manage', $encounter);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['pause', 'resume', 'sign', 'cancel'])],
            'reason' => ['nullable', 'required_if:action,cancel', 'string', 'max:1000'],
        ]);
        $workflow->transition($encounter, $validated['action'], $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Encounter updated.');
    }

    public function amendment(Request $request, ClinicalEncounter $encounter, ClinicalEncounterWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('manage', $encounter);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:10000'],
        ]);
        $workflow->amend($encounter, $validated, $request->user());

        return back()->with('success', 'Amendment added.');
    }

    private function payload(ClinicalEncounter $encounter): array
    {
        return $encounter->load([
            'patient.allergies.recorder:id,firstname,lastname',
            'patient.alerts.recorder:id,firstname,lastname',
            'visit.queueEntry',
            'appointment',
            'clinician.user:id,firstname,lastname',
            'vitals.recorder:id,firstname,lastname',
            'diagnoses',
            'events',
            'amendments',
        ])->toArray();
    }

    private function timeline(Patient $patient): array
    {
        return collect()
            ->merge($patient->allergies()->latest('recorded_at')->get()->map(fn ($item) => ['type' => 'allergy', 'label' => $item->substance, 'occurred_at' => $item->recorded_at]))
            ->merge($patient->alerts()->latest('recorded_at')->get()->map(fn ($item) => ['type' => 'alert', 'label' => $item->title, 'occurred_at' => $item->recorded_at]))
            ->merge(EncounterVital::where('patient_id', $patient->id)->latest('measured_at')->get()->map(fn ($item) => ['type' => 'vitals', 'label' => 'Vitals recorded', 'occurred_at' => $item->measured_at]))
            ->merge(ClinicalEncounter::where('patient_id', $patient->id)->latest('started_at')->get()->map(fn ($item) => ['type' => 'encounter', 'label' => $item->status, 'occurred_at' => $item->started_at]))
            ->sortByDesc('occurred_at')
            ->values()
            ->all();
    }
}
