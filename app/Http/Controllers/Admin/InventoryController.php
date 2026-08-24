<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\Facility;
use App\Models\InventoryAdjustmentRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryTransferRequest;
use App\Models\InventoryUnit;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\AuditService;
use App\Services\InventoryLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends FoundationController
{
    public function catalogue(): Response
    {
        $this->authorize('viewAny', InventoryItem::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Inventory/Catalogue', $this->shared($hospital->id) + [
            'items' => InventoryItem::with('baseUnit')->where('hospital_id', $hospital->id)->latest()->get(),
            'units' => InventoryUnit::with('baseUnit')->where('hospital_id', $hospital->id)->orderBy('name')->get(),
            'locations' => InventoryLocation::with('facility:id,name')->where('hospital_id', $hospital->id)->orderBy('name')->get(),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $this->authorize('manageCatalogue', InventoryItem::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)], 'code' => ['required', 'string', 'max:50', Rule::unique('inventory_locations')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'type' => ['required', Rule::in(['main_store', 'pharmacy', 'ward_store', 'other'])]]);
        $location = InventoryLocation::create($validated + ['hospital_id' => $hospital->id, 'is_active' => true]);
        app(AuditService::class)->record('inventory.location_created', $location, null, $location->toArray(), actor: $request->user());

        return back()->with('success', 'Inventory location created.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $this->authorize('manageCatalogue', InventoryItem::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['code' => ['required', 'string', 'max:50', Rule::unique('inventory_units')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'base_unit_id' => ['nullable', Rule::exists('inventory_units', 'id')->where('hospital_id', $hospital->id)], 'base_factor' => ['required', 'numeric', 'gt:0', 'max:1000000']]);
        $unit = InventoryUnit::create($validated + ['hospital_id' => $hospital->id, 'requires_pharmacist_validation' => true, 'is_active' => true]);
        app(AuditService::class)->record('inventory.unit_created', $unit, null, $unit->toArray(), actor: $request->user());

        return back()->with('success', 'Inventory unit created.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorize('manageCatalogue', InventoryItem::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'base_unit_id' => ['required', Rule::exists('inventory_units', 'id')->where('hospital_id', $hospital->id)],
            'billable_service_id' => ['nullable', Rule::exists('billable_services', 'id')->where('hospital_id', $hospital->id)],
            'sku' => ['required', 'string', 'max:80', Rule::unique('inventory_items')->where('hospital_id', $hospital->id)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('inventory_items')->where('hospital_id', $hospital->id)],
            'type' => ['required', Rule::in(['medicine', 'supply', 'equipment', 'other'])],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'dosage_form' => ['nullable', 'string', 'max:120'],
            'strength' => ['nullable', 'string', 'max:120'],
            'route' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
        ]);
        $item = InventoryItem::create($validated + ['hospital_id' => $hospital->id, 'requires_pharmacist_validation' => true, 'is_active' => true]);
        app(AuditService::class)->record('inventory.item_created', $item, null, $item->toArray(), actor: $request->user());

        return back()->with('success', 'Inventory item created.');
    }

    public function stock(): Response
    {
        $this->authorize('viewAny', InventoryItem::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Inventory/Stock', $this->shared($hospital->id) + [
            'batches' => InventoryBatch::with('item')->where('hospital_id', $hospital->id)->latest()->get(),
            'balances' => StockBalance::with(['location', 'item', 'batch'])->where('hospital_id', $hospital->id)->where('quantity', '>', 0)->latest()->get(),
            'movements' => StockMovement::with(['item', 'batch'])->where('hospital_id', $hospital->id)->latest('posted_at')->limit(50)->get(),
        ]);
    }

    public function receiveBatch(Request $request, InventoryLedgerService $ledger): RedirectResponse
    {
        $this->authorize('receive', InventoryItem::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate([
            'inventory_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('hospital_id', $hospital->id)],
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('hospital_id', $hospital->id)],
            'inventory_unit_id' => ['required', Rule::exists('inventory_units', 'id')->where('hospital_id', $hospital->id)],
            'batch_number' => ['required', 'string', 'max:120'],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'unit_cost_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'state' => ['required', Rule::in(['quarantine', 'available'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $ledger->receiveBatch($validated + ['hospital_id' => $hospital->id], $request->user());

        return back()->with('success', 'Batch received through stock ledger.');
    }

    public function setBatchState(Request $request, InventoryBatch $batch, InventoryLedgerService $ledger): RedirectResponse
    {
        $this->authorize('adjust', $batch->item);
        abort_unless($batch->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['state' => ['required', Rule::in(['quarantine', 'available', 'expired', 'damaged', 'recalled', 'exhausted'])], 'reason' => ['required', 'string', 'max:1000']]);
        $ledger->setBatchState($batch, $validated['state'], $request->user(), $validated['reason']);

        return back()->with('success', 'Batch state updated.');
    }

    public function transfers(): Response
    {
        $this->authorize('viewAny', InventoryItem::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Inventory/Transfers', $this->shared($hospital->id) + [
            'transfers' => InventoryTransferRequest::with(['item', 'batch'])->where('hospital_id', $hospital->id)->latest()->get(),
            'balances' => StockBalance::with(['location', 'item', 'batch'])->where('hospital_id', $hospital->id)->where('quantity', '>', 0)->get(),
        ]);
    }

    public function storeTransfer(Request $request, InventoryLedgerService $ledger): RedirectResponse
    {
        $this->authorize('transfer', InventoryItem::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('hospital_id', $hospital->id)], 'inventory_batch_id' => ['required', Rule::exists('inventory_batches', 'id')->where('hospital_id', $hospital->id)], 'from_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('hospital_id', $hospital->id)], 'to_location_id' => ['required', 'different:from_location_id', Rule::exists('inventory_locations', 'id')->where('hospital_id', $hospital->id)], 'quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $ledger->requestTransfer($validated + ['hospital_id' => $hospital->id], $request->user());

        return back()->with('success', 'Transfer requested.');
    }

    public function transferAction(Request $request, InventoryTransferRequest $transfer, InventoryLedgerService $ledger): RedirectResponse
    {
        $this->authorize('transfer', $transfer->item);
        abort_unless($transfer->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['action' => ['required', Rule::in(['dispatch', 'receive', 'cancel'])], 'reason' => ['nullable', 'required_if:action,cancel', 'string', 'max:1000']]);
        match ($validated['action']) {
            'dispatch' => $ledger->dispatchTransfer($transfer, $request->user()),
            'receive' => $ledger->receiveTransfer($transfer, $request->user()),
            'cancel' => $ledger->cancelTransfer($transfer, $request->user(), $validated['reason']),
        };

        return back()->with('success', 'Transfer updated.');
    }

    public function adjustments(): Response
    {
        $this->authorize('viewAny', InventoryItem::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Inventory/Adjustments', $this->shared($hospital->id) + [
            'adjustments' => InventoryAdjustmentRequest::with(['item', 'batch'])->where('hospital_id', $hospital->id)->latest()->get(),
            'balances' => StockBalance::with(['location', 'item', 'batch'])->where('hospital_id', $hospital->id)->where('quantity', '>', 0)->get(),
        ]);
    }

    public function storeAdjustment(Request $request, InventoryLedgerService $ledger): RedirectResponse
    {
        $this->authorize('adjust', InventoryItem::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['inventory_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('hospital_id', $hospital->id)], 'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('hospital_id', $hospital->id)], 'inventory_batch_id' => ['required', Rule::exists('inventory_batches', 'id')->where('hospital_id', $hospital->id)], 'quantity_delta' => ['required', 'numeric', 'not_in:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $ledger->requestAdjustment($validated + ['hospital_id' => $hospital->id], $request->user());

        return back()->with('success', 'Adjustment requested.');
    }

    public function approveAdjustment(Request $request, InventoryAdjustmentRequest $adjustment, InventoryLedgerService $ledger): RedirectResponse
    {
        $this->authorize('approveAdjustment', InventoryItem::findOrFail($adjustment->inventory_item_id));
        abort_unless($adjustment->hospital_id === $this->currentHospital()->id, 403);
        $ledger->approveAdjustment($adjustment, $request->user());

        return back()->with('success', 'Adjustment approved and posted.');
    }

    public function reverseMovement(Request $request, StockMovement $movement, InventoryLedgerService $ledger): RedirectResponse
    {
        $this->authorize('adjust', $movement->item);
        abort_unless($movement->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $ledger->reverseMovement($movement, $request->user(), $validated['reason']);

        return back()->with('success', 'Reversal movement posted.');
    }

    public function reports(InventoryLedgerService $ledger): Response
    {
        $this->authorize('viewAny', InventoryItem::class);
        $hospital = $this->currentHospital();
        $items = InventoryItem::with('baseUnit')->where('hospital_id', $hospital->id)->get();

        return Inertia::render('Admin/Inventory/Reports', $this->shared($hospital->id) + [
            'lowStock' => $items->map(fn ($item) => ['item' => $item, 'quantity' => (float) StockBalance::where('inventory_item_id', $item->id)->sum('quantity')])->filter(fn ($row) => $row['quantity'] <= (float) $row['item']->reorder_level)->values(),
            'nearExpiry' => InventoryBatch::with('item')->where('hospital_id', $hospital->id)->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays(90)])->orderBy('expiry_date')->get(),
            'expired' => InventoryBatch::with('item')->where('hospital_id', $hospital->id)->whereNotNull('expiry_date')->where('expiry_date', '<', today())->orderBy('expiry_date')->get(),
            'fefo' => $items->map(fn ($item) => ['item' => $item, 'batches' => $ledger->fefoBatches($item)->take(5)->values()])->filter(fn ($row) => $row['batches']->isNotEmpty())->values(),
        ]);
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'units' => InventoryUnit::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'items' => InventoryItem::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'batches' => InventoryBatch::with('item')->where('hospital_id', $hospitalId)->orderByDesc('created_at')->get(),
            'billableServices' => BillableService::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }
}
