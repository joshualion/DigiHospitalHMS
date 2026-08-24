<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admission;
use App\Models\InpatientChart;
use App\Models\InpatientDischargeSummary;
use App\Models\InpatientHandoverRecord;
use App\Models\InpatientOrder;
use App\Models\InpatientProgressNote;
use App\Services\InpatientChartWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InpatientChartController extends FoundationController
{
    public function index(): Response
    {
        $this->authorize('viewAny', InpatientChart::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Inpatient/Index', [
            'admissions' => Admission::with(['patient.allergies', 'patient.alerts', 'ward', 'bed', 'chart'])
                ->where('hospital_id', $hospital->id)
                ->whereIn('status', ['admitted', 'transferred'])
                ->latest('admitted_at')
                ->get(),
            'tasks' => InpatientOrder::with('chart.patient')
                ->where('hospital_id', $hospital->id)
                ->whereIn('status', ['active', 'acknowledged'])
                ->latest()
                ->get(),
        ]);
    }

    public function open(Admission $admission, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        abort_unless($admission->hospital_id === $this->currentHospital()->id, 403);
        $chart = $workflow->chartForAdmission($admission, request()->user());
        $this->authorize('view', $chart);

        return redirect()->route('admin.inpatient.charts.show', $chart);
    }

    public function show(InpatientChart $chart): Response
    {
        $this->authorize('view', $chart);

        return Inertia::render('Admin/Inpatient/Chart', [
            'chart' => $this->payload($chart),
            'timeline' => $this->timeline($chart),
        ]);
    }

    public function progressNote(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $chart);
        $validated = $request->validate(['note_type' => ['required', Rule::in(['soap', 'ward_round', 'review', 'procedure_note', 'other'])], 'subjective' => ['nullable', 'string', 'max:10000'], 'objective' => ['nullable', 'string', 'max:10000'], 'assessment' => ['nullable', 'string', 'max:10000'], 'plan' => ['nullable', 'string', 'max:10000'], 'narrative' => ['nullable', 'string', 'max:10000']]);
        $workflow->progressNote($chart, $validated, $request->user());

        return back()->with('success', 'Progress note saved.');
    }

    public function signProgressNote(InpatientProgressNote $note, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('sign', $note->chart);
        $workflow->signProgressNote($note, request()->user());

        return back()->with('success', 'Progress note signed.');
    }

    public function amendProgressNote(Request $request, InpatientProgressNote $note, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $note->chart);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'content' => ['required', 'string', 'max:10000']]);
        $workflow->amend($note, $validated, $request->user());

        return back()->with('success', 'Amendment added.');
    }

    public function nursingNote(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $chart);
        $validated = $request->validate(['shift' => ['nullable', 'string', 'max:80'], 'note' => ['required', 'string', 'max:10000']]);
        $workflow->nursingNote($chart, $validated, $request->user());

        return back()->with('success', 'Nursing note recorded.');
    }

    public function observation(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $chart);
        $validated = $request->validate(['temperature' => ['nullable', 'numeric'], 'temperature_unit' => ['required', Rule::in(['C', 'F'])], 'pulse' => ['nullable', 'integer', 'min:0', 'max:300'], 'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:100'], 'blood_pressure_systolic' => ['nullable', 'integer', 'min:0', 'max:300'], 'blood_pressure_diastolic' => ['nullable', 'integer', 'min:0', 'max:200'], 'oxygen_saturation' => ['nullable', 'integer', 'min:0', 'max:100'], 'pain_score' => ['nullable', 'integer', 'min:0', 'max:10'], 'glucose' => ['nullable', 'numeric'], 'glucose_unit' => ['nullable', 'string', 'max:20'], 'consciousness_notes' => ['nullable', 'string', 'max:2000'], 'observed_at' => ['required', 'date']]);
        $workflow->observation($chart, $validated, $request->user());

        return back()->with('success', 'Observation recorded.');
    }

    public function intakeOutput(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $chart);
        $validated = $request->validate(['direction' => ['required', Rule::in(['intake', 'output'])], 'measurement_type' => ['required', 'string', 'max:120'], 'quantity' => ['required', 'numeric', 'min:0'], 'unit' => ['required', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:2000'], 'measured_at' => ['required', 'date']]);
        $workflow->intakeOutput($chart, $validated, $request->user());

        return back()->with('success', 'Intake/output recorded.');
    }

    public function carePlan(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $chart);
        $validated = $request->validate(['problem' => ['required', 'string', 'max:3000'], 'goal' => ['nullable', 'string', 'max:3000'], 'intervention' => ['nullable', 'string', 'max:3000'], 'evaluation' => ['nullable', 'string', 'max:3000'], 'status' => ['required', Rule::in(['active', 'met', 'not_met', 'discontinued'])]]);
        $workflow->carePlan($chart, $validated, $request->user());

        return back()->with('success', 'Care plan recorded.');
    }

    public function diagnosis(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $chart);
        $validated = $request->validate(['description' => ['required', 'string', 'max:2000'], 'coding_system' => ['nullable', 'string', 'max:80'], 'code' => ['nullable', 'string', 'max:80'], 'status' => ['required', Rule::in(['provisional', 'confirmed', 'resolved'])]]);
        $workflow->diagnosis($chart, $validated, $request->user());

        return back()->with('success', 'Problem list updated.');
    }

    public function order(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('orders', $chart);
        $validated = $request->validate(['order_type' => ['required', Rule::in(['nursing_care', 'monitoring', 'diet', 'activity', 'investigation'])], 'instruction' => ['required', 'string', 'max:5000'], 'status' => ['required', Rule::in(['draft', 'active'])]]);
        $workflow->order($chart, $validated, $request->user());

        return back()->with('success', 'Order recorded.');
    }

    public function orderTransition(Request $request, InpatientOrder $order, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('orders', $order->chart);
        $validated = $request->validate(['action' => ['required', Rule::in(['activate', 'acknowledge', 'complete', 'discontinue', 'cancel'])], 'reason' => ['nullable', 'string', 'max:1000']]);
        $workflow->transitionOrder($order, $validated['action'], $request->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Order updated.');
    }

    public function handover(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('handover', $chart);
        $validated = $request->validate(['from_shift' => ['required', 'string', 'max:80'], 'to_shift' => ['required', 'string', 'max:80'], 'summary' => ['required', 'string', 'max:10000']]);
        $workflow->handover($chart, $validated, $request->user());

        return back()->with('success', 'Handover recorded.');
    }

    public function acknowledgeHandover(InpatientHandoverRecord $handover, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('handover', $handover->chart);
        $workflow->acknowledgeHandover($handover, request()->user());

        return back()->with('success', 'Handover acknowledged.');
    }

    public function dischargeSummary(Request $request, InpatientChart $chart, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('document', $chart);
        $validated = $request->validate(['admission_summary' => ['nullable', 'string', 'max:10000'], 'diagnosis_summary' => ['nullable', 'string', 'max:10000'], 'results_summary' => ['nullable', 'string', 'max:10000'], 'clinical_course' => ['nullable', 'string', 'max:10000'], 'discharge_plan' => ['nullable', 'string', 'max:10000']]);
        $workflow->dischargeSummary($chart, $validated, $request->user());

        return back()->with('success', 'Discharge summary drafted.');
    }

    public function signDischargeSummary(InpatientDischargeSummary $summary, InpatientChartWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('signDischargeSummary', $summary->chart);
        $workflow->signDischargeSummary($summary, request()->user());

        return back()->with('success', 'Discharge summary signed.');
    }

    private function payload(InpatientChart $chart): array
    {
        return $chart->load(['admission.ward', 'admission.bed', 'patient.allergies', 'patient.alerts', 'ward', 'bed', 'progressNotes.amendments', 'nursingNotes', 'observations', 'intakeOutputs', 'carePlans', 'diagnoses', 'orders', 'handovers', 'dischargeSummary.amendments'])->toArray();
    }

    private function timeline(InpatientChart $chart): array
    {
        return collect()
            ->merge($chart->progressNotes()->latest('authored_at')->get()->map(fn ($item) => ['type' => 'progress', 'label' => $item->note_type.' '.$item->status, 'occurred_at' => $item->authored_at]))
            ->merge($chart->nursingNotes()->latest('authored_at')->get()->map(fn ($item) => ['type' => 'nursing', 'label' => $item->shift ?: 'Nursing note', 'occurred_at' => $item->authored_at]))
            ->merge($chart->observations()->latest('observed_at')->get()->map(fn ($item) => ['type' => 'observation', 'label' => 'Observation chart', 'occurred_at' => $item->observed_at]))
            ->merge($chart->orders()->latest('ordered_at')->get()->map(fn ($item) => ['type' => 'order', 'label' => $item->order_type.' '.$item->status, 'occurred_at' => $item->ordered_at]))
            ->merge($chart->handovers()->latest('authored_at')->get()->map(fn ($item) => ['type' => 'handover', 'label' => $item->from_shift.' to '.$item->to_shift, 'occurred_at' => $item->authored_at]))
            ->sortByDesc('occurred_at')
            ->values()
            ->all();
    }
}
