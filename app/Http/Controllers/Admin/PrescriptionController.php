<?php

namespace App\Http\Controllers\Admin;

use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionDispense;
use App\Models\PrescriptionItem;
use App\Services\InventoryLedgerService;
use App\Services\PrescriptionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PrescriptionController extends FoundationController
{
    public function index(): Response
    {
        $this->authorize('viewAny', Prescription::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Pharmacy/Prescriptions', $this->shared($hospital->id) + [
            'prescriptions' => Prescription::with(['patient', 'items.item', 'reviews'])->where('hospital_id', $hospital->id)->latest()->paginate(15),
        ]);
    }

    public function store(Request $request, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('create', Prescription::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'facility_id' => ['required', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)],
            'patient_id' => ['required', Rule::exists('patients', 'id')->where('hospital_id', $hospital->id)],
            'clinical_encounter_id' => ['nullable', Rule::exists('clinical_encounters', 'id')->where('hospital_id', $hospital->id)],
            'clinical_note' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('hospital_id', $hospital->id)],
            'items.*.inventory_unit_id' => ['nullable', Rule::exists('inventory_units', 'id')->where('hospital_id', $hospital->id)],
            'items.*.dose' => ['required', 'string', 'max:120'],
            'items.*.route' => ['nullable', 'string', 'max:120'],
            'items.*.frequency' => ['nullable', 'string', 'max:120'],
            'items.*.duration' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.instructions' => ['nullable', 'string', 'max:2000'],
            'items.*.indication' => ['nullable', 'string', 'max:1000'],
            'items.*.is_prn' => ['boolean'],
            'items.*.medication_order_type' => ['nullable', Rule::in(['regular', 'once', 'stat', 'prn'])],
            'items.*.scheduled_times' => ['nullable', 'array'],
            'items.*.scheduled_times.*' => ['string', 'max:5'],
            'items.*.start_at' => ['nullable', 'date'],
            'items.*.end_at' => ['nullable', 'date', 'after_or_equal:items.*.start_at'],
            'items.*.prn_instructions' => ['nullable', 'string', 'max:1000'],
        ]);
        $prescription = $workflow->createDraft($validated + ['hospital_id' => $hospital->id], $request->user());

        return redirect()->route('admin.prescriptions.show', $prescription)->with('success', 'Prescription drafted.');
    }

    public function show(Prescription $prescription, InventoryLedgerService $ledger): Response
    {
        $this->authorize('view', $prescription);
        $prescription->load(['patient.allergies', 'patient.alerts', 'items.item', 'items.unit', 'reviews', 'dispenses.batch', 'amendments', 'invoice.lines']);

        return Inertia::render('Admin/Pharmacy/PrescriptionShow', $this->shared($prescription->hospital_id) + [
            'prescription' => $prescription,
            'fefo' => $prescription->items->map(fn ($item) => ['prescription_item_id' => $item->id, 'batches' => $ledger->fefoBatches($item->item)->take(10)->values()]),
        ]);
    }

    public function sign(Prescription $prescription, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('sign', $prescription);
        $workflow->sign($prescription, request()->user());

        return back()->with('success', 'Prescription signed.');
    }

    public function transition(Request $request, Prescription $prescription, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('sign', $prescription);
        $validated = $request->validate(['status' => ['required', Rule::in(['discontinued', 'cancelled'])], 'reason' => ['required', 'string', 'max:1000']]);
        $workflow->transition($prescription, $validated['status'], $request->user(), $validated['reason']);

        return back()->with('success', 'Prescription updated.');
    }

    public function amend(Request $request, Prescription $prescription, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('sign', $prescription);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'content' => ['required', 'string', 'max:5000']]);
        $workflow->amend($prescription, $validated, $request->user());

        return back()->with('success', 'Prescription amendment recorded.');
    }

    public function review(Request $request, Prescription $prescription, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('review', $prescription);
        $validated = $request->validate(['action' => ['required', Rule::in(['approved', 'clarification_requested', 'rejected', 'substitution_authorized'])], 'prescription_item_id' => ['nullable', Rule::exists('prescription_items', 'id')->where('prescription_id', $prescription->id)], 'reason' => ['nullable', 'string', 'max:2000'], 'substituted_inventory_item_id' => ['nullable', Rule::exists('inventory_items', 'id')->where('hospital_id', $prescription->hospital_id)], 'substitution_note' => ['nullable', 'string', 'max:2000']]);
        $workflow->review($prescription, $validated, $request->user());

        return back()->with('success', 'Prescription review recorded.');
    }

    public function bill(Prescription $prescription, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('review', $prescription);
        $workflow->bill($prescription, request()->user(), $this->currentHospital()->default_currency ?? 'NGN');

        return back()->with('success', 'Prescription invoice prepared.');
    }

    public function dispense(Request $request, PrescriptionItem $item, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('dispense', $item->prescription);
        $validated = $request->validate(['inventory_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('hospital_id', $item->hospital_id)], 'inventory_batch_id' => ['required', Rule::exists('inventory_batches', 'id')->where('hospital_id', $item->hospital_id)], 'quantity' => ['required', 'numeric', 'gt:0'], 'instructions' => ['nullable', 'string', 'max:2000']]);
        $workflow->dispense($item, InventoryLocation::findOrFail($validated['inventory_location_id']), InventoryBatch::findOrFail($validated['inventory_batch_id']), $validated['quantity'], $request->user(), $validated['instructions'] ?? null);

        return back()->with('success', 'Medicine dispensed.');
    }

    public function returnDispense(Request $request, PrescriptionDispense $dispense, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('reverse', $dispense->prescription);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $workflow->returnDispense($dispense, $request->user(), $validated['reason']);

        return back()->with('success', 'Patient return recorded.');
    }

    public function reverseDispense(Request $request, PrescriptionDispense $dispense, PrescriptionWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('reverse', $dispense->prescription);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $workflow->reverseDispense($dispense, $request->user(), $validated['reason']);

        return back()->with('success', 'Dispense reversed.');
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->get(['id', 'name']),
            'patients' => Patient::where('hospital_id', $hospitalId)->latest()->limit(50)->get(['id', 'hospital_number', 'first_name', 'middle_name', 'last_name']),
            'encounters' => ClinicalEncounter::where('hospital_id', $hospitalId)->latest()->limit(50)->get(['id', 'patient_id', 'status']),
            'items' => InventoryItem::where('hospital_id', $hospitalId)->where('type', 'medicine')->where('is_active', true)->get(),
            'units' => InventoryUnit::where('hospital_id', $hospitalId)->where('is_active', true)->get(),
            'locations' => InventoryLocation::where('hospital_id', $hospitalId)->where('is_active', true)->get(),
            'batches' => InventoryBatch::where('hospital_id', $hospitalId)->get(),
        ];
    }
}
