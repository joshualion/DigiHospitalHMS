<?php

namespace App\Http\Controllers\Admin;

use App\Models\Facility;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\ProcurementApprovalLimit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Services\ProcurementWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends FoundationController
{
    public function index(ProcurementWorkflowService $workflow): Response
    {
        $this->authorize('viewAny', Supplier::class);
        $hospital = $this->currentHospital();

        return Inertia::render('Admin/Procurement/Index', $this->shared($hospital->id) + [
            'suppliers' => Supplier::with('items:id,name,sku')->where('hospital_id', $hospital->id)->latest()->get(),
            'requisitions' => PurchaseRequisition::with(['location', 'lines.item', 'purchaseOrder'])->where('hospital_id', $hospital->id)->latest()->get(),
            'purchaseOrders' => PurchaseOrder::with(['supplier', 'lines.item', 'goodsReceipts.lines'])->where('hospital_id', $hospital->id)->latest()->get(),
            'reorderSuggestions' => $workflow->reorderSuggestions($hospital->id),
        ]);
    }

    public function storeSupplier(Request $request, ProcurementWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('manage', Supplier::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['code' => ['required', 'string', 'max:50', Rule::unique('suppliers')->where('hospital_id', $hospital->id)], 'name' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])], 'contact_person' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:80'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:1000'], 'payment_terms' => ['nullable', 'string', 'max:255'], 'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'], 'notes' => ['nullable', 'string', 'max:2000'], 'item_ids' => ['array'], 'item_ids.*' => [Rule::exists('inventory_items', 'id')->where('hospital_id', $hospital->id)]]);
        $workflow->createSupplier($validated + ['hospital_id' => $hospital->id], $request->user());

        return back()->with('success', 'Supplier saved.');
    }

    public function storeLimit(Request $request): RedirectResponse
    {
        $this->authorize('approve', Supplier::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['role_name' => ['required', 'string', 'max:80'], 'limit_minor' => ['required', 'integer', 'min:0'], 'currency' => ['required', 'string', 'size:3']]);
        ProcurementApprovalLimit::updateOrCreate(['hospital_id' => $hospital->id, 'role_name' => $validated['role_name'], 'currency' => strtoupper($validated['currency'])], ['limit_minor' => $validated['limit_minor'], 'is_active' => true]);

        return back()->with('success', 'Approval limit saved.');
    }

    public function storeRequisition(Request $request, ProcurementWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('requisition', Supplier::class);
        $hospital = $this->currentHospital();
        $validated = $request->validate(['facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $hospital->id)], 'inventory_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('hospital_id', $hospital->id)], 'currency' => ['required', 'string', 'size:3'], 'reason' => ['nullable', 'string', 'max:1000'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('hospital_id', $hospital->id)], 'lines.*.inventory_unit_id' => ['nullable', Rule::exists('inventory_units', 'id')->where('hospital_id', $hospital->id)], 'lines.*.quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.estimated_unit_cost_minor' => ['required', 'integer', 'min:0'], 'lines.*.discount_minor' => ['nullable', 'integer', 'min:0'], 'lines.*.tax_minor' => ['nullable', 'integer', 'min:0'], 'lines.*.notes' => ['nullable', 'string', 'max:1000']]);
        $requisition = $workflow->createRequisition($validated + ['hospital_id' => $hospital->id], $request->user());

        return redirect()->route('admin.procurement.index', ['requisition' => $requisition->id])->with('success', 'Requisition drafted.');
    }

    public function requisitionAction(Request $request, PurchaseRequisition $requisition, ProcurementWorkflowService $workflow): RedirectResponse
    {
        abort_unless($requisition->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['action' => ['required', Rule::in(['submit', 'approve', 'reject', 'convert'])], 'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('hospital_id', $requisition->hospital_id)], 'reason' => ['nullable', 'required_if:action,reject', 'string', 'max:1000']]);
        match ($validated['action']) {
            'submit' => tap($this->authorize('requisition', Supplier::class), fn () => $workflow->submit($requisition, $request->user())),
            'approve' => tap($this->authorize('approve', Supplier::class), fn () => $workflow->approve($requisition, $request->user(), $validated['reason'] ?? null)),
            'reject' => tap($this->authorize('approve', Supplier::class), fn () => $workflow->reject($requisition, $request->user(), $validated['reason'])),
            'convert' => tap($this->authorize('approve', Supplier::class), fn () => $workflow->convertToPurchaseOrder($requisition, Supplier::findOrFail($validated['supplier_id']), $request->user())),
        };

        return back()->with('success', 'Requisition updated.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, ProcurementWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('receive', $purchaseOrder->supplier);
        abort_unless($purchaseOrder->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['facility_id' => ['nullable', Rule::exists('facilities', 'id')->where('hospital_id', $purchaseOrder->hospital_id)], 'inventory_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('hospital_id', $purchaseOrder->hospital_id)], 'delivery_reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000'], 'allow_over_receipt' => ['boolean'], 'lines' => ['required', 'array', 'min:1'], 'lines.*.purchase_order_line_id' => ['required', Rule::exists('purchase_order_lines', 'id')->where('purchase_order_id', $purchaseOrder->id)], 'lines.*.batch_number' => ['required', 'string', 'max:120'], 'lines.*.manufacture_date' => ['nullable', 'date'], 'lines.*.expiry_date' => ['nullable', 'date'], 'lines.*.received_quantity' => ['required', 'numeric', 'gt:0'], 'lines.*.accepted_quantity' => ['required', 'numeric', 'min:0'], 'lines.*.rejected_quantity' => ['required', 'numeric', 'min:0'], 'lines.*.unit_cost_minor' => ['nullable', 'integer', 'min:0'], 'lines.*.requires_clearance' => ['boolean'], 'lines.*.batch_state' => ['nullable', Rule::in(['quarantine', 'available'])], 'lines.*.rejection_reason' => ['nullable', 'string', 'max:1000']]);
        $workflow->receive($purchaseOrder, $validated, $request->user());

        return back()->with('success', 'Goods receipt posted.');
    }

    public function returnLine(Request $request, GoodsReceiptLine $line, ProcurementWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('reverse', Supplier::class);
        abort_unless($line->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['inventory_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('hospital_id', $line->hospital_id)], 'quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'max:1000']]);
        $workflow->supplierReturn($line, InventoryLocation::findOrFail($validated['inventory_location_id']), $validated['quantity'], $request->user(), $validated['reason']);

        return back()->with('success', 'Supplier return posted.');
    }

    public function reverseLine(Request $request, GoodsReceiptLine $line, ProcurementWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('reverse', Supplier::class);
        abort_unless($line->hospital_id === $this->currentHospital()->id, 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $workflow->reverseReceiptLine($line, $request->user(), $validated['reason']);

        return back()->with('success', 'Receipt reversal posted.');
    }

    private function shared(int $hospitalId): array
    {
        return [
            'facilities' => Facility::where('hospital_id', $hospitalId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'items' => InventoryItem::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'units' => InventoryUnit::where('hospital_id', $hospitalId)->where('is_active', true)->orderBy('name')->get(),
            'approvalLimits' => ProcurementApprovalLimit::where('hospital_id', $hospitalId)->where('is_active', true)->get(),
        ];
    }
}
