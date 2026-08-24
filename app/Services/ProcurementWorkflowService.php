<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\NumberSequence;
use App\Models\ProcurementApprovalLimit;
use App\Models\ProcurementEvent;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionLine;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProcurementWorkflowService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly InventoryLedgerService $ledger,
        private readonly AuditService $audit,
    ) {}

    public function createSupplier(array $data, User $actor): Supplier
    {
        $itemIds = collect($data['item_ids'] ?? [])->filter()->values();
        unset($data['item_ids']);
        $supplier = Supplier::create($data);
        if ($itemIds->isNotEmpty()) {
            $supplier->items()->sync($itemIds->mapWithKeys(fn ($id): array => [(int) $id => ['hospital_id' => $supplier->hospital_id]])->all());
        }
        $this->event($supplier, 'procurement.supplier_created', null, $supplier->load('items')->toArray(), $actor);

        return $supplier->refresh();
    }

    public function createRequisition(array $data, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $actor): PurchaseRequisition {
            $lines = collect($data['lines']);
            $totals = $this->totals($lines);
            $requisition = PurchaseRequisition::create([
                'hospital_id' => $data['hospital_id'],
                'facility_id' => $data['facility_id'] ?? null,
                'inventory_location_id' => $data['inventory_location_id'],
                'status' => 'draft',
                'currency' => $data['currency'] ?? 'NGN',
                'subtotal_minor' => $totals['subtotal_minor'],
                'discount_minor' => $totals['discount_minor'],
                'tax_minor' => $totals['tax_minor'],
                'total_minor' => $totals['total_minor'],
                'reason' => $data['reason'] ?? null,
                'created_by' => $actor->id,
            ]);
            foreach ($lines as $row) {
                $item = InventoryItem::where('hospital_id', $data['hospital_id'])->findOrFail($row['inventory_item_id']);
                PurchaseRequisitionLine::create([
                    'hospital_id' => $data['hospital_id'],
                    'purchase_requisition_id' => $requisition->id,
                    'inventory_item_id' => $item->id,
                    'inventory_unit_id' => $row['inventory_unit_id'] ?? $item->base_unit_id,
                    'quantity' => $row['quantity'],
                    'estimated_unit_cost_minor' => $row['estimated_unit_cost_minor'],
                    'discount_minor' => $row['discount_minor'] ?? 0,
                    'tax_minor' => $row['tax_minor'] ?? 0,
                    'line_total_minor' => $this->lineTotal($row),
                    'notes' => $row['notes'] ?? null,
                ]);
            }
            $this->event($requisition, 'procurement.requisition_created', null, $requisition->fresh('lines')->toArray(), $actor);

            return $requisition->refresh();
        });
    }

    public function submit(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        abort_unless($requisition->status === 'draft', 422, 'Only draft requisitions can be submitted.');
        abort_if($requisition->lines()->count() === 0, 422, 'Cannot submit an empty requisition.');
        $before = $requisition->toArray();
        $requisition->forceFill(['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
        $this->event($requisition, 'procurement.requisition_submitted', $before, $requisition->fresh()->toArray(), $actor);

        return $requisition->refresh();
    }

    public function approve(PurchaseRequisition $requisition, User $actor, ?string $reason = null): PurchaseRequisition
    {
        abort_unless($requisition->status === 'submitted', 422, 'Only submitted requisitions can be approved.');
        abort_if($requisition->created_by === $actor->id || $requisition->submitted_by === $actor->id, 403, 'Approval must be separate from requester.');
        $this->assertApprovalLimit($requisition, $actor);
        $before = $requisition->toArray();
        $requisition->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'decision_reason' => $reason])->save();
        $this->event($requisition, 'procurement.requisition_approved', $before, $requisition->fresh()->toArray(), $actor, $reason);

        return $requisition->refresh();
    }

    public function reject(PurchaseRequisition $requisition, User $actor, string $reason): PurchaseRequisition
    {
        abort_unless($requisition->status === 'submitted', 422, 'Only submitted requisitions can be rejected.');
        abort_if($requisition->created_by === $actor->id || $requisition->submitted_by === $actor->id, 403, 'Decision maker must be separate from requester.');
        $before = $requisition->toArray();
        $requisition->forceFill(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(), 'decision_reason' => $reason])->save();
        $this->event($requisition, 'procurement.requisition_rejected', $before, $requisition->fresh()->toArray(), $actor, $reason);

        return $requisition->refresh();
    }

    public function convertToPurchaseOrder(PurchaseRequisition $requisition, Supplier $supplier, User $actor): PurchaseOrder
    {
        abort_unless($requisition->status === 'approved', 422, 'Only approved requisitions can become purchase orders.');
        abort_if($requisition->converted_purchase_order_id, 422, 'Requisition already has a purchase order.');
        abort_unless($supplier->hospital_id === $requisition->hospital_id && $supplier->status === 'active', 422, 'Supplier is not active for this hospital.');

        return DB::transaction(function () use ($requisition, $supplier, $actor): PurchaseOrder {
            $requisition = PurchaseRequisition::whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            abort_if($requisition->converted_purchase_order_id, 422, 'Requisition already has a purchase order.');
            $po = PurchaseOrder::create([
                'hospital_id' => $requisition->hospital_id,
                'facility_id' => $requisition->facility_id,
                'supplier_id' => $supplier->id,
                'purchase_requisition_id' => $requisition->id,
                'purchase_order_number' => $this->allocate($requisition->hospital_id, 'purchase_order_number'),
                'status' => 'approved',
                'currency' => $requisition->currency,
                'supplier_snapshot' => $supplier->only(['id', 'code', 'name', 'contact_person', 'phone', 'email', 'payment_terms', 'lead_time_days']),
                'subtotal_minor' => $requisition->subtotal_minor,
                'discount_minor' => $requisition->discount_minor,
                'tax_minor' => $requisition->tax_minor,
                'total_minor' => $requisition->total_minor,
                'created_by' => $actor->id,
                'approved_by' => $requisition->approved_by,
                'approved_at' => $requisition->approved_at,
            ]);
            foreach ($requisition->lines()->with(['item', 'unit'])->get() as $line) {
                PurchaseOrderLine::create([
                    'hospital_id' => $line->hospital_id,
                    'purchase_order_id' => $po->id,
                    'purchase_requisition_line_id' => $line->id,
                    'inventory_item_id' => $line->inventory_item_id,
                    'inventory_unit_id' => $line->inventory_unit_id,
                    'item_snapshot' => $line->item->only(['id', 'sku', 'name', 'type', 'generic_name', 'brand_name', 'dosage_form', 'strength', 'route']),
                    'quantity' => $line->quantity,
                    'unit_cost_minor' => $line->estimated_unit_cost_minor,
                    'discount_minor' => $line->discount_minor,
                    'tax_minor' => $line->tax_minor,
                    'line_total_minor' => $line->line_total_minor,
                    'notes' => $line->notes,
                ]);
            }
            $requisition->forceFill(['converted_purchase_order_id' => $po->id])->save();
            $this->event($po, 'procurement.purchase_order_created', null, $po->fresh('lines')->toArray(), $actor);

            return $po->refresh();
        });
    }

    public function receive(PurchaseOrder $purchaseOrder, array $data, User $actor): GoodsReceipt
    {
        abort_unless(in_array($purchaseOrder->status, ['approved', 'partially_received'], true), 422, 'Purchase order cannot receive goods.');

        return DB::transaction(function () use ($purchaseOrder, $data, $actor): GoodsReceipt {
            $purchaseOrder = PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $receipt = GoodsReceipt::create([
                'hospital_id' => $purchaseOrder->hospital_id,
                'facility_id' => $data['facility_id'] ?? $purchaseOrder->facility_id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'inventory_location_id' => $data['inventory_location_id'],
                'grn_number' => $this->allocate($purchaseOrder->hospital_id, 'goods_receipt_number'),
                'status' => 'posted',
                'delivery_reference' => $data['delivery_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $actor->id,
                'received_at' => now(),
            ]);
            $location = InventoryLocation::where('hospital_id', $purchaseOrder->hospital_id)->findOrFail($data['inventory_location_id']);
            foreach ($data['lines'] as $row) {
                $this->receiveLine($purchaseOrder, $receipt, $location, $row, $actor, (bool) ($data['allow_over_receipt'] ?? false));
            }
            $this->refreshPurchaseOrderStatus($purchaseOrder);
            $this->event($receipt, 'procurement.goods_received', null, $receipt->fresh('lines')->toArray(), $actor);

            return $receipt->refresh();
        });
    }

    public function supplierReturn(GoodsReceiptLine $line, InventoryLocation $location, float|string $quantity, User $actor, string $reason): SupplierReturn
    {
        abort_if(bccomp((string) $quantity, '0', 4) <= 0, 422, 'Return quantity must be positive.');
        abort_if(bccomp((string) $quantity, (string) $line->accepted_quantity, 4) > 0, 422, 'Cannot return more than accepted quantity.');

        return DB::transaction(function () use ($line, $location, $quantity, $actor, $reason): SupplierReturn {
            $line = GoodsReceiptLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $movement = $this->ledger->postMovement('supplier_return', $line->purchaseOrderLine->item, $line->batch, $location, null, $line->purchaseOrderLine->unit, $quantity, $actor, $reason, $line->unit_cost_minor, $line->goodsReceipt->purchaseOrder->currency, $line);
            $return = SupplierReturn::create(['hospital_id' => $line->hospital_id, 'goods_receipt_line_id' => $line->id, 'inventory_location_id' => $location->id, 'inventory_batch_id' => $line->inventory_batch_id, 'stock_movement_id' => $movement->id, 'quantity' => $quantity, 'action' => 'supplier_return', 'reason' => $reason, 'performed_by' => $actor->id, 'performed_at' => now()]);
            $this->event($return, 'procurement.supplier_returned', null, $return->toArray(), $actor, $reason);

            return $return;
        });
    }

    public function reverseReceiptLine(GoodsReceiptLine $line, User $actor, string $reason): SupplierReturn
    {
        abort_unless($line->stock_movement_id, 422, 'Receipt line has no accepted stock movement.');
        abort_if(StockMovement::where('reverses_movement_id', $line->stock_movement_id)->exists(), 422, 'Receipt line is already reversed.');

        return DB::transaction(function () use ($line, $actor, $reason): SupplierReturn {
            $line = GoodsReceiptLine::whereKey($line->id)->lockForUpdate()->firstOrFail();
            $movement = $this->ledger->reverseMovement(StockMovement::findOrFail($line->stock_movement_id), $actor, $reason);
            $return = SupplierReturn::create(['hospital_id' => $line->hospital_id, 'goods_receipt_line_id' => $line->id, 'inventory_location_id' => $line->goodsReceipt->inventory_location_id, 'inventory_batch_id' => $line->inventory_batch_id, 'stock_movement_id' => $movement->id, 'quantity' => $line->accepted_quantity, 'action' => 'receipt_reversal', 'reason' => $reason, 'performed_by' => $actor->id, 'performed_at' => now()]);
            $poLine = PurchaseOrderLine::whereKey($line->purchase_order_line_id)->lockForUpdate()->firstOrFail();
            $poLine->forceFill([
                'received_quantity' => bcsub((string) $poLine->received_quantity, (string) $line->received_quantity, 4),
                'accepted_quantity' => bcsub((string) $poLine->accepted_quantity, (string) $line->accepted_quantity, 4),
                'rejected_quantity' => bcsub((string) $poLine->rejected_quantity, (string) $line->rejected_quantity, 4),
            ])->save();
            $this->refreshPurchaseOrderStatus($poLine->purchaseOrder);
            $this->event($return, 'procurement.receipt_reversed', null, $return->toArray(), $actor, $reason);

            return $return;
        });
    }

    public function reorderSuggestions(int $hospitalId): Collection
    {
        return InventoryItem::with('baseUnit')->where('hospital_id', $hospitalId)->where('is_active', true)->get()
            ->map(function (InventoryItem $item): array {
                $onHand = (float) StockBalance::where('inventory_item_id', $item->id)->sum('quantity');
                $onOrder = PurchaseOrderLine::where('inventory_item_id', $item->id)->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', ['approved', 'partially_received']))->get()
                    ->sum(fn (PurchaseOrderLine $line): float => (float) $line->outstandingQuantity());

                return ['item' => $item, 'on_hand' => $onHand, 'on_order' => $onOrder, 'suggested_quantity' => max(0, (float) $item->reorder_level - $onHand - $onOrder)];
            })
            ->filter(fn (array $row): bool => $row['suggested_quantity'] > 0)
            ->values();
    }

    private function receiveLine(PurchaseOrder $purchaseOrder, GoodsReceipt $receipt, InventoryLocation $location, array $row, User $actor, bool $allowOverReceipt): GoodsReceiptLine
    {
        $poLine = PurchaseOrderLine::where('purchase_order_id', $purchaseOrder->id)->whereKey($row['purchase_order_line_id'])->lockForUpdate()->firstOrFail();
        abort_unless($poLine->hospital_id === $purchaseOrder->hospital_id, 403);
        $received = (string) $row['received_quantity'];
        $accepted = (string) ($row['accepted_quantity'] ?? 0);
        $rejected = (string) ($row['rejected_quantity'] ?? 0);
        abort_unless(bccomp(bcadd($accepted, $rejected, 4), $received, 4) === 0, 422, 'Accepted and rejected quantities must equal received quantity.');
        abort_if(bccomp($rejected, '0', 4) > 0 && empty($row['rejection_reason']), 422, 'Rejected goods require a reason.');
        if (bccomp($received, $poLine->outstandingQuantity(), 4) > 0) {
            abort_unless($allowOverReceipt && $actor->can('procurement.over-receive'), 403, 'Over-receipt requires explicit permission.');
        }
        $batchState = ($row['requires_clearance'] ?? false) || $poLine->item->requires_pharmacist_validation ? 'quarantine' : ($row['batch_state'] ?? 'available');
        abort_unless(in_array($batchState, ['quarantine', 'available'], true), 422, 'Received batches must start as quarantine or available.');
        $batch = InventoryBatch::firstOrCreate(
            ['hospital_id' => $purchaseOrder->hospital_id, 'inventory_item_id' => $poLine->inventory_item_id, 'batch_number' => $row['batch_number']],
            ['manufacture_date' => $row['manufacture_date'] ?? null, 'expiry_date' => $row['expiry_date'] ?? null, 'supplier_reference' => $receipt->supplier?->code, 'currency' => $purchaseOrder->currency, 'unit_cost_minor' => $row['unit_cost_minor'] ?? $poLine->unit_cost_minor, 'state' => $batchState],
        );
        $movement = null;
        if (bccomp($accepted, '0', 4) > 0) {
            $movement = $this->ledger->postMovement('goods_receipt', $poLine->item, $batch, null, $location, $poLine->unit, $accepted, $actor, 'Goods receipt '.$receipt->grn_number, $row['unit_cost_minor'] ?? $poLine->unit_cost_minor, $purchaseOrder->currency, $receipt);
        }
        $line = GoodsReceiptLine::create(['hospital_id' => $purchaseOrder->hospital_id, 'goods_receipt_id' => $receipt->id, 'purchase_order_line_id' => $poLine->id, 'inventory_item_id' => $poLine->inventory_item_id, 'inventory_unit_id' => $poLine->inventory_unit_id, 'inventory_batch_id' => $batch->id, 'stock_movement_id' => $movement?->id, 'batch_number' => $row['batch_number'], 'manufacture_date' => $row['manufacture_date'] ?? null, 'expiry_date' => $row['expiry_date'] ?? null, 'received_quantity' => $received, 'accepted_quantity' => $accepted, 'rejected_quantity' => $rejected, 'unit_cost_minor' => $row['unit_cost_minor'] ?? $poLine->unit_cost_minor, 'batch_state' => $batchState, 'rejection_reason' => $row['rejection_reason'] ?? null]);
        $poLine->forceFill(['received_quantity' => bcadd((string) $poLine->received_quantity, $received, 4), 'accepted_quantity' => bcadd((string) $poLine->accepted_quantity, $accepted, 4), 'rejected_quantity' => bcadd((string) $poLine->rejected_quantity, $rejected, 4)])->save();

        return $line;
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $fresh = $purchaseOrder->fresh('lines');
        $status = $fresh->lines->every(fn (PurchaseOrderLine $line): bool => bccomp($line->outstandingQuantity(), '0', 4) <= 0) ? 'fully_received' : ($fresh->lines->contains(fn (PurchaseOrderLine $line): bool => bccomp((string) $line->received_quantity, '0', 4) > 0) ? 'partially_received' : 'approved');
        $fresh->forceFill(['status' => $status, 'closed_at' => $status === 'fully_received' ? now() : null])->save();
    }

    private function totals(Collection $lines): array
    {
        return $lines->reduce(function (array $carry, array $line): array {
            $subtotal = (int) round((float) $line['quantity'] * (int) $line['estimated_unit_cost_minor']);
            $discount = (int) ($line['discount_minor'] ?? 0);
            $tax = (int) ($line['tax_minor'] ?? 0);

            return [
                'subtotal_minor' => $carry['subtotal_minor'] + $subtotal,
                'discount_minor' => $carry['discount_minor'] + $discount,
                'tax_minor' => $carry['tax_minor'] + $tax,
                'total_minor' => $carry['total_minor'] + max(0, $subtotal - $discount) + $tax,
            ];
        }, ['subtotal_minor' => 0, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 0]);
    }

    private function lineTotal(array $line): int
    {
        $subtotal = (int) round((float) $line['quantity'] * (int) $line['estimated_unit_cost_minor']);

        return max(0, $subtotal - (int) ($line['discount_minor'] ?? 0)) + (int) ($line['tax_minor'] ?? 0);
    }

    private function assertApprovalLimit(PurchaseRequisition $requisition, User $actor): void
    {
        if ($actor->hasRole('superadmin')) {
            return;
        }
        $roles = $actor->roles->pluck('name');
        $limit = ProcurementApprovalLimit::where('hospital_id', $requisition->hospital_id)->where('currency', $requisition->currency)->where('is_active', true)->whereIn('role_name', $roles)->max('limit_minor');
        abort_unless($limit !== null && (int) $limit >= (int) $requisition->total_minor, 403, 'Requisition exceeds approval limit.');
    }

    private function allocate(int $hospitalId, string $key): string
    {
        return $this->numbers->allocate(NumberSequence::where('hospital_id', $hospitalId)->whereNull('facility_id')->where('key', $key)->where('status', 'active')->firstOrFail());
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        ProcurementEvent::create(['hospital_id' => $subject->hospital_id, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'actor_id' => $actor->id, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
