<?php

namespace App\Services;

use App\Models\InventoryAdjustmentRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryEvent;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryTransferRequest;
use App\Models\InventoryUnit;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryLedgerService
{
    public function __construct(private readonly AuditService $audit) {}

    public function baseQuantity(float|string $quantity, InventoryUnit $unit): string
    {
        return bcmul((string) $quantity, (string) $unit->base_factor, 4);
    }

    public function receiveBatch(array $data, User $actor): InventoryBatch
    {
        return DB::transaction(function () use ($data, $actor): InventoryBatch {
            $item = InventoryItem::where('hospital_id', $data['hospital_id'])->findOrFail($data['inventory_item_id']);
            $unit = InventoryUnit::where('hospital_id', $data['hospital_id'])->findOrFail($data['inventory_unit_id'] ?? $item->base_unit_id);
            $location = InventoryLocation::where('hospital_id', $data['hospital_id'])->findOrFail($data['inventory_location_id']);
            $batch = InventoryBatch::firstOrCreate(
                ['hospital_id' => $data['hospital_id'], 'inventory_item_id' => $item->id, 'batch_number' => $data['batch_number']],
                ['manufacture_date' => $data['manufacture_date'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null, 'supplier_reference' => $data['supplier_reference'] ?? null, 'currency' => $data['currency'] ?? 'NGN', 'unit_cost_minor' => $data['unit_cost_minor'] ?? null, 'state' => $data['state'] ?? 'available'],
            );
            abort_unless(in_array($batch->state, ['quarantine', 'available'], true), 422, 'Only quarantine or available batches can receive opening stock.');
            $this->postMovement('opening_balance', $item, $batch, null, $location, $unit, $data['quantity'], $actor, $data['reason'] ?? 'Opening balance', $batch->unit_cost_minor, $batch->currency);
            $this->event($batch, 'inventory.batch_received', null, $batch->fresh()->toArray(), $actor, $data['reason'] ?? null);

            return $batch->refresh();
        });
    }

    public function setBatchState(InventoryBatch $batch, string $state, User $actor, ?string $reason = null): InventoryBatch
    {
        abort_unless(in_array($state, ['quarantine', 'available', 'expired', 'damaged', 'recalled', 'exhausted'], true), 422, 'Invalid batch state.');
        $before = $batch->toArray();
        $batch->forceFill(['state' => $state])->save();
        $this->event($batch, 'inventory.batch_state_changed', $before, $batch->fresh()->toArray(), $actor, $reason);

        return $batch->refresh();
    }

    public function requestTransfer(array $data, User $actor): InventoryTransferRequest
    {
        $transfer = InventoryTransferRequest::create($data + ['requested_by' => $actor->id, 'requested_at' => now(), 'status' => 'requested']);
        $this->event($transfer, 'inventory.transfer_requested', null, $transfer->toArray(), $actor, $data['reason'] ?? null);

        return $transfer;
    }

    public function dispatchTransfer(InventoryTransferRequest $transfer, User $actor): InventoryTransferRequest
    {
        abort_unless($transfer->status === 'requested', 422, 'Only requested transfers can be dispatched.');

        return DB::transaction(function () use ($transfer, $actor): InventoryTransferRequest {
            $transfer = InventoryTransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $item = InventoryItem::findOrFail($transfer->inventory_item_id);
            $batch = InventoryBatch::findOrFail($transfer->inventory_batch_id);
            abort_unless($batch->isDispensableCandidate(), 422, 'Batch is not available for stock movement.');
            $unit = $item->baseUnit;
            $this->postMovement('transfer_dispatch', $item, $batch, InventoryLocation::findOrFail($transfer->from_location_id), null, $unit, $transfer->quantity, $actor, $transfer->reason, $batch->unit_cost_minor, $batch->currency, $transfer);
            $before = $transfer->toArray();
            $transfer->forceFill(['status' => 'dispatched', 'dispatched_by' => $actor->id, 'dispatched_at' => now()])->save();
            $this->event($transfer, 'inventory.transfer_dispatched', $before, $transfer->fresh()->toArray(), $actor, $transfer->reason);

            return $transfer->refresh();
        });
    }

    public function receiveTransfer(InventoryTransferRequest $transfer, User $actor): InventoryTransferRequest
    {
        abort_unless($transfer->status === 'dispatched', 422, 'Only dispatched transfers can be received.');

        return DB::transaction(function () use ($transfer, $actor): InventoryTransferRequest {
            $transfer = InventoryTransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $item = InventoryItem::findOrFail($transfer->inventory_item_id);
            $batch = InventoryBatch::findOrFail($transfer->inventory_batch_id);
            $this->postMovement('transfer_receipt', $item, $batch, null, InventoryLocation::findOrFail($transfer->to_location_id), $item->baseUnit, $transfer->quantity, $actor, $transfer->reason, $batch->unit_cost_minor, $batch->currency, $transfer);
            $before = $transfer->toArray();
            $transfer->forceFill(['status' => 'received', 'received_by' => $actor->id, 'received_at' => now()])->save();
            $this->event($transfer, 'inventory.transfer_received', $before, $transfer->fresh()->toArray(), $actor, $transfer->reason);

            return $transfer->refresh();
        });
    }

    public function cancelTransfer(InventoryTransferRequest $transfer, User $actor, string $reason): InventoryTransferRequest
    {
        abort_unless($transfer->status === 'requested', 422, 'Only requested transfers can be cancelled.');
        $before = $transfer->toArray();
        $transfer->forceFill(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason])->save();
        $this->event($transfer, 'inventory.transfer_cancelled', $before, $transfer->fresh()->toArray(), $actor, $reason);

        return $transfer->refresh();
    }

    public function requestAdjustment(array $data, User $actor): InventoryAdjustmentRequest
    {
        $adjustment = InventoryAdjustmentRequest::create($data + ['requested_by' => $actor->id, 'requested_at' => now(), 'status' => 'requested']);
        $this->event($adjustment, 'inventory.adjustment_requested', null, $adjustment->toArray(), $actor, $data['reason']);

        return $adjustment;
    }

    public function approveAdjustment(InventoryAdjustmentRequest $adjustment, User $actor): InventoryAdjustmentRequest
    {
        abort_unless($adjustment->status === 'requested', 422, 'Only requested adjustments can be approved.');
        abort_if($adjustment->requested_by === $actor->id, 403, 'Adjustment approver must be separate from requester.');

        return DB::transaction(function () use ($adjustment, $actor): InventoryAdjustmentRequest {
            $adjustment = InventoryAdjustmentRequest::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            $item = InventoryItem::findOrFail($adjustment->inventory_item_id);
            $batch = InventoryBatch::findOrFail($adjustment->inventory_batch_id);
            $from = (float) $adjustment->quantity_delta < 0 ? InventoryLocation::findOrFail($adjustment->inventory_location_id) : null;
            $to = (float) $adjustment->quantity_delta > 0 ? InventoryLocation::findOrFail($adjustment->inventory_location_id) : null;
            $this->postMovement('adjustment', $item, $batch, $from, $to, $item->baseUnit, abs((float) $adjustment->quantity_delta), $actor, $adjustment->reason, $batch->unit_cost_minor, $batch->currency, $adjustment);
            $before = $adjustment->toArray();
            $adjustment->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->event($adjustment, 'inventory.adjustment_approved', $before, $adjustment->fresh()->toArray(), $actor, $adjustment->reason);

            return $adjustment->refresh();
        });
    }

    public function reverseMovement(StockMovement $movement, User $actor, string $reason): StockMovement
    {
        abort_if($movement->reverses_movement_id || StockMovement::where('reverses_movement_id', $movement->id)->exists(), 422, 'Movement is already reversed.');
        $from = $movement->to_location_id ? InventoryLocation::find($movement->to_location_id) : null;
        $to = $movement->from_location_id ? InventoryLocation::find($movement->from_location_id) : null;

        return $this->postMovement('reversal', $movement->item, $movement->batch, $from, $to, InventoryUnit::findOrFail($movement->inventory_unit_id), $movement->quantity, $actor, $reason, $movement->unit_cost_minor, $movement->currency, $movement, $movement->id);
    }

    public function fefoBatches(InventoryItem $item, ?InventoryLocation $location = null): Collection
    {
        return StockBalance::with(['batch', 'location'])
            ->where('hospital_id', $item->hospital_id)
            ->where('inventory_item_id', $item->id)
            ->when($location, fn ($query) => $query->where('inventory_location_id', $location->id))
            ->where('quantity', '>', 0)
            ->whereHas('batch', fn ($query) => $query->where('state', 'available')->where(function ($nested): void {
                $nested->whereNull('expiry_date')->orWhere('expiry_date', '>=', today());
            }))
            ->get()
            ->sortBy(fn ($balance) => $balance->batch->expiry_date?->format('Y-m-d') ?? '9999-12-31')
            ->values();
    }

    public function postMovement(string $type, InventoryItem $item, InventoryBatch $batch, ?InventoryLocation $from, ?InventoryLocation $to, InventoryUnit $unit, float|string $quantity, User $actor, ?string $reason = null, ?int $unitCostMinor = null, string $currency = 'NGN', ?Model $reference = null, ?int $reversesMovementId = null): StockMovement
    {
        return DB::transaction(function () use ($type, $item, $batch, $from, $to, $unit, $quantity, $actor, $reason, $unitCostMinor, $currency, $reference, $reversesMovementId): StockMovement {
            $baseQuantity = $this->baseQuantity($quantity, $unit);
            abort_if(bccomp($baseQuantity, '0', 4) <= 0, 422, 'Stock movement quantity must be positive.');
            if ($from) {
                $this->changeBalance($item, $batch, $from, bcmul($baseQuantity, '-1', 4));
            }
            if ($to) {
                $this->changeBalance($item, $batch, $to, $baseQuantity);
            }
            $movement = StockMovement::create(['hospital_id' => $item->hospital_id, 'inventory_item_id' => $item->id, 'inventory_batch_id' => $batch->id, 'from_location_id' => $from?->id, 'to_location_id' => $to?->id, 'inventory_unit_id' => $unit->id, 'movement_type' => $type, 'quantity' => $quantity, 'base_quantity' => $baseQuantity, 'unit_cost_minor' => $unitCostMinor, 'currency' => $currency, 'reference_type' => $reference ? $reference::class : null, 'reference_id' => $reference?->getKey(), 'reverses_movement_id' => $reversesMovementId, 'reason' => $reason, 'posted_by' => $actor->id, 'posted_at' => now()]);
            $this->event($movement, 'inventory.movement_posted', null, $movement->toArray(), $actor, $reason);

            return $movement;
        });
    }

    private function changeBalance(InventoryItem $item, InventoryBatch $batch, InventoryLocation $location, string $delta): StockBalance
    {
        $balance = StockBalance::where('hospital_id', $item->hospital_id)
            ->where('inventory_location_id', $location->id)
            ->where('inventory_item_id', $item->id)
            ->where('inventory_batch_id', $batch->id)
            ->lockForUpdate()
            ->first();
        if (! $balance) {
            $balance = StockBalance::create(['hospital_id' => $item->hospital_id, 'inventory_location_id' => $location->id, 'inventory_item_id' => $item->id, 'inventory_batch_id' => $batch->id, 'quantity' => 0]);
        }
        $newQuantity = bcadd((string) $balance->quantity, $delta, 4);
        abort_if(bccomp($newQuantity, '0', 4) < 0, 422, 'Stock balance cannot become negative.');
        $balance->forceFill(['quantity' => $newQuantity])->save();

        return $balance->refresh();
    }

    private function event(Model $subject, string $action, ?array $before, ?array $after, User $actor, ?string $reason = null): void
    {
        InventoryEvent::create(['hospital_id' => $subject->hospital_id, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'actor_id' => $actor->id, 'action' => $action, 'before' => $before, 'after' => $after, 'reason' => $reason, 'occurred_at' => now()]);
        $this->audit->record($action, $subject, $before, $after, actor: $actor, reason: $reason);
    }
}
