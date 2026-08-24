<?php

namespace App\Http\Controllers\Admin;

use App\Models\EmarAdministration;
use App\Models\EmarSchedule;
use App\Models\InpatientChart;
use App\Services\EmarWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmarController extends FoundationController
{
    public function index(): Response
    {
        $this->authorize('viewAny', EmarSchedule::class);
        $hospital = $this->currentHospital();
        $now = now();

        return Inertia::render('Admin/Emar/Index', [
            'charts' => InpatientChart::with(['admission.ward', 'admission.bed', 'patient.allergies', 'patient.alerts'])
                ->where('hospital_id', $hospital->id)
                ->where('status', 'active')
                ->latest('opened_at')
                ->get(),
            'doses' => EmarSchedule::with(['chart.patient', 'prescriptionItem'])
                ->where('hospital_id', $hospital->id)
                ->whereIn('status', ['pending', 'delayed', 'prn_available'])
                ->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')
                ->get()
                ->map(fn (EmarSchedule $schedule): array => $this->dosePayload($schedule, $now)),
        ]);
    }

    public function show(InpatientChart $chart, EmarWorkflowService $workflow): Response
    {
        $this->authorize('viewAny', EmarSchedule::class);
        abort_unless($chart->hospital_id === $this->currentHospital()->id, 403);
        $workflow->syncSchedules($chart, request()->user());

        return Inertia::render('Admin/Emar/Chart', [
            'chart' => $chart->load(['admission.ward', 'admission.bed', 'patient.allergies', 'patient.alerts', 'emarSchedules.administration.amendments', 'emarSchedules.prescriptionItem.dispenses.batch', 'emarAdministrations.batch', 'emarAdministrations.amendments']),
            'doses' => $chart->emarSchedules()->with(['administration.amendments', 'prescriptionItem.dispenses.batch'])->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')->get()->map(fn (EmarSchedule $schedule): array => $this->dosePayload($schedule, now())),
        ]);
    }

    public function sync(InpatientChart $chart, EmarWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('viewAny', EmarSchedule::class);
        abort_unless($chart->hospital_id === $this->currentHospital()->id, 403);
        $workflow->syncSchedules($chart, request()->user());

        return back()->with('success', 'eMAR schedule refreshed.');
    }

    public function administer(Request $request, EmarSchedule $schedule, EmarWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('administer', $schedule);
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['administered', 'omitted', 'refused', 'held', 'unavailable', 'delayed', 'not-given'])],
            'actual_at' => ['required', 'date'],
            'quantity_administered' => ['nullable', 'numeric', 'gt:0'],
            'prescription_dispense_id' => ['nullable', Rule::exists('prescription_dispenses', 'id')->where('prescription_item_id', $schedule->prescription_item_id)->where('action', 'dispense')],
            'confirmation' => ['required', 'array'],
            'confirmation.patient' => ['accepted'],
            'confirmation.medication' => ['accepted'],
            'confirmation.dose' => ['accepted'],
            'confirmation.route' => ['accepted'],
            'confirmation.timing' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'prn_indication' => ['nullable', 'string', 'max:1000'],
            'prn_response' => ['nullable', 'string', 'max:1000'],
        ]);
        $workflow->administer($schedule, $validated, $request->user());

        return back()->with('success', 'Medication administration recorded.');
    }

    public function amend(Request $request, EmarAdministration $administration, EmarWorkflowService $workflow): RedirectResponse
    {
        $schedule = $administration->schedule ?: EmarSchedule::whereKey($administration->emar_schedule_id)->firstOrFail();
        $this->authorize('amend', $schedule);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'content' => ['required', 'string', 'max:5000']]);
        $workflow->amend($administration, $validated, $request->user());

        return back()->with('success', 'eMAR correction recorded.');
    }

    private function dosePayload(EmarSchedule $schedule, mixed $now): array
    {
        $state = 'upcoming';
        if ($schedule->is_prn) {
            $state = 'prn';
        } elseif ($schedule->scheduled_at?->lt($now->copy()->subMinutes(30))) {
            $state = 'overdue';
        } elseif ($schedule->scheduled_at?->between($now->copy()->subMinutes(30), $now->copy()->addMinutes(30))) {
            $state = 'due-now';
        }

        return $schedule->toArray() + ['due_state' => $state];
    }
}
