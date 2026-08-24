<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryAdjustmentRequest;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\StaffProfile;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryLedgerService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase5AInventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private User $storekeeper;

    private User $pharmacist;

    private InventoryUnit $each;

    private InventoryUnit $pack;

    private InventoryItem $item;

    private InventoryLocation $mainStore;

    private InventoryLocation $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['default_currency' => 'NGN']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'status' => 'active']);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        $this->storekeeper = $this->staffUser(['inventory.view', 'inventory.catalogue.manage', 'inventory.stock.receive', 'inventory.stock.transfer', 'inventory.stock.adjust'], 'storekeeper');
        $this->pharmacist = $this->staffUser(['inventory.view', 'inventory.catalogue.manage', 'inventory.stock.receive', 'inventory.stock.transfer', 'inventory.stock.adjust', 'inventory.adjustments.approve'], 'pharmacist');

        $this->each = InventoryUnit::create(['hospital_id' => $this->hospital->id, 'code' => 'EACH', 'name' => 'Each', 'base_factor' => 1, 'is_active' => true]);
        $this->pack = InventoryUnit::create(['hospital_id' => $this->hospital->id, 'code' => 'PACK10', 'name' => 'Pack of 10', 'base_unit_id' => $this->each->id, 'base_factor' => 10, 'is_active' => true]);
        $this->item = InventoryItem::create(['hospital_id' => $this->hospital->id, 'base_unit_id' => $this->each->id, 'sku' => 'AMOX-500', 'barcode' => '1234567890', 'type' => 'medicine', 'generic_name' => 'Configured generic', 'name' => 'Configured medicine', 'dosage_form' => 'Configured form', 'strength' => 'Configured strength', 'route' => 'Configured route', 'reorder_level' => 15, 'is_active' => true]);
        $this->mainStore = InventoryLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'MAIN', 'name' => 'Main Store', 'type' => 'main_store', 'is_active' => true]);
        $this->pharmacy = InventoryLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'PHARM', 'name' => 'Pharmacy', 'type' => 'pharmacy', 'is_active' => true]);
    }

    public function test_unit_conversion_batch_uniqueness_and_stock_receipt_ledger(): void
    {
        $ledger = app(InventoryLedgerService::class);
        $batch = $ledger->receiveBatch(['hospital_id' => $this->hospital->id, 'inventory_location_id' => $this->mainStore->id, 'inventory_item_id' => $this->item->id, 'inventory_unit_id' => $this->pack->id, 'batch_number' => 'BATCH-001', 'expiry_date' => now()->addYear()->toDateString(), 'unit_cost_minor' => 2500, 'state' => 'available', 'quantity' => 3, 'reason' => 'Opening balance'], $this->storekeeper);

        $this->assertSame('30.0000', StockBalance::where('inventory_batch_id', $batch->id)->first()->quantity);
        $this->assertSame('30.0000', StockMovement::first()->base_quantity);
        $this->assertDatabaseHas('inventory_batches', ['batch_number' => 'BATCH-001', 'unit_cost_minor' => 2500]);
        $this->assertDatabaseHas('inventory_events', ['action' => 'inventory.movement_posted']);

        $this->expectException(QueryException::class);
        InventoryBatch::create(['hospital_id' => $this->hospital->id, 'inventory_item_id' => $this->item->id, 'batch_number' => 'BATCH-001', 'state' => 'available']);
    }

    public function test_expiry_status_negative_stock_and_fefo_rules(): void
    {
        $ledger = app(InventoryLedgerService::class);
        $later = $this->receive('LATER', 5, now()->addMonths(6)->toDateString());
        $earlier = $this->receive('EARLIER', 5, now()->addMonth()->toDateString());
        $expired = $this->receive('OLD', 5, now()->subDay()->toDateString());
        $ledger->setBatchState($expired, 'expired', $this->pharmacist, 'Expired report');

        $this->assertSame('EARLIER', $ledger->fefoBatches($this->item, $this->mainStore)->first()->batch->batch_number);
        $this->assertFalse($expired->refresh()->isDispensableCandidate());

        try {
            $ledger->postMovement('issue', $this->item, $earlier, $this->mainStore, null, $this->each, 999, $this->storekeeper, 'Negative stock attempt');
            $this->fail('Expected negative stock prevention.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('LATER', $later->batch_number);
    }

    public function test_transfer_dispatch_receipt_adjustment_approval_and_reversal(): void
    {
        $ledger = app(InventoryLedgerService::class);
        $batch = $this->receive('MOVE', 20, now()->addYear()->toDateString());
        $transfer = $ledger->requestTransfer(['hospital_id' => $this->hospital->id, 'inventory_item_id' => $this->item->id, 'inventory_batch_id' => $batch->id, 'from_location_id' => $this->mainStore->id, 'to_location_id' => $this->pharmacy->id, 'quantity' => 8, 'reason' => 'Restock pharmacy'], $this->storekeeper);
        $ledger->dispatchTransfer($transfer, $this->storekeeper);
        $ledger->receiveTransfer($transfer->fresh(), $this->pharmacist);

        $this->assertSame('12.0000', StockBalance::where('inventory_location_id', $this->mainStore->id)->where('inventory_batch_id', $batch->id)->first()->quantity);
        $this->assertSame('8.0000', StockBalance::where('inventory_location_id', $this->pharmacy->id)->where('inventory_batch_id', $batch->id)->first()->quantity);

        $adjustment = $ledger->requestAdjustment(['hospital_id' => $this->hospital->id, 'inventory_location_id' => $this->pharmacy->id, 'inventory_item_id' => $this->item->id, 'inventory_batch_id' => $batch->id, 'quantity_delta' => -2, 'reason' => 'Damaged during count'], $this->storekeeper);
        $ledger->approveAdjustment($adjustment, $this->pharmacist);
        $this->assertSame('6.0000', StockBalance::where('inventory_location_id', $this->pharmacy->id)->where('inventory_batch_id', $batch->id)->first()->quantity);

        $movement = StockMovement::where('movement_type', 'adjustment')->firstOrFail();
        $ledger->reverseMovement($movement, $this->pharmacist, 'Correction reversal');
        $this->assertSame('8.0000', StockBalance::where('inventory_location_id', $this->pharmacy->id)->where('inventory_batch_id', $batch->id)->first()->quantity);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'reversal', 'reverses_movement_id' => $movement->id]);
    }

    public function test_adjustment_approval_separation_authorization_scoping_and_pages(): void
    {
        $batch = $this->receive('AUTH', 3, now()->addYear()->toDateString());
        $adjustment = InventoryAdjustmentRequest::create(['hospital_id' => $this->hospital->id, 'inventory_location_id' => $this->mainStore->id, 'inventory_item_id' => $this->item->id, 'inventory_batch_id' => $batch->id, 'quantity_delta' => 1, 'status' => 'requested', 'reason' => 'Count correction', 'requested_by' => $this->storekeeper->id, 'requested_at' => now()]);

        $this->expectException(HttpException::class);
        app(InventoryLedgerService::class)->approveAdjustment($adjustment, $this->storekeeper);
    }

    public function test_inventory_pages_and_idor_are_enforced(): void
    {
        $viewer = $this->staffUser(['inventory.view'], 'nurse');
        $this->actingAs($viewer)->post('/admin/inventory/items', ['sku' => 'DENIED'])->assertForbidden();

        $this->actingAs($this->storekeeper)->get('/admin/inventory/catalogue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory/Catalogue')->has('items')->has('locations'));
        $this->actingAs($this->storekeeper)->get('/admin/inventory/stock')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory/Stock')->has('balances')->has('movements'));
        $this->actingAs($this->storekeeper)->get('/admin/inventory/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory/Reports')->has('lowStock')->has('fefo'));

        $otherHospital = Hospital::factory()->create();
        $otherUnit = InventoryUnit::create(['hospital_id' => $otherHospital->id, 'code' => 'OTHER', 'name' => 'Other', 'base_factor' => 1, 'is_active' => true]);
        $otherItem = InventoryItem::create(['hospital_id' => $otherHospital->id, 'base_unit_id' => $otherUnit->id, 'sku' => 'OTHER', 'name' => 'Other item', 'type' => 'supply', 'is_active' => true]);
        $this->actingAs($this->storekeeper)->patch('/admin/inventory/batches/999999/state', ['state' => 'available', 'reason' => 'Nope'])->assertNotFound();
        $this->assertFalse($this->storekeeper->can('view', $otherItem));
    }

    private function receive(string $batchNumber, float $quantity, string $expiry): InventoryBatch
    {
        return app(InventoryLedgerService::class)->receiveBatch(['hospital_id' => $this->hospital->id, 'inventory_location_id' => $this->mainStore->id, 'inventory_item_id' => $this->item->id, 'inventory_unit_id' => $this->each->id, 'batch_number' => $batchNumber, 'expiry_date' => $expiry, 'unit_cost_minor' => 1000, 'state' => 'available', 'quantity' => $quantity, 'reason' => 'Opening balance'], $this->storekeeper);
    }

    private function staffUser(array $permissions, string $role): User
    {
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->syncRoles([$role]);
        $user->givePermissionTo($permissions);
        StaffProfile::factory()->create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'is_active' => true]);

        return $user->load('staffProfile');
    }
}
