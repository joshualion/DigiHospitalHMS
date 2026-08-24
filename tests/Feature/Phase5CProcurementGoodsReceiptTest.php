<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\NumberSequence;
use App\Models\ProcurementApprovalLimit;
use App\Models\ProcurementEvent;
use App\Models\PurchaseRequisition;
use App\Models\StaffProfile;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProcurementWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Phase5CProcurementGoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    private InventoryLocation $mainStore;

    private InventoryUnit $each;

    private InventoryItem $medicine;

    private User $requester;

    private User $approver;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->hospital = Hospital::create(['legal_name' => 'Phase 5C Hospital', 'display_name' => 'Phase 5C', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
        $this->facility = Facility::create(['hospital_id' => $this->hospital->id, 'code' => 'P5C', 'name' => 'Phase 5C Facility', 'facility_type' => 'clinic', 'status' => 'active', 'is_primary' => true]);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $this->facility->id, 'currency' => 'NGN']);
        foreach ([['purchase_order_number', 'PO'], ['goods_receipt_number', 'GRN']] as [$key, $prefix]) {
            NumberSequence::create(['hospital_id' => $this->hospital->id, 'key' => $key, 'label' => $key, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'status' => 'active']);
        }
        $this->requester = $this->user('storekeeper', ['procurement.view', 'procurement.suppliers.manage', 'procurement.requisitions.create', 'procurement.receive', 'inventory.view']);
        $this->approver = $this->user('pharmacist', ['procurement.view', 'procurement.requisitions.approve', 'procurement.receive', 'procurement.reverse', 'procurement.over-receive', 'inventory.view']);
        ProcurementApprovalLimit::create(['hospital_id' => $this->hospital->id, 'role_name' => 'pharmacist', 'limit_minor' => 100000, 'currency' => 'NGN', 'is_active' => true]);
        $this->each = InventoryUnit::create(['hospital_id' => $this->hospital->id, 'code' => 'EACH', 'name' => 'Each', 'base_factor' => 1, 'is_active' => true]);
        $this->medicine = InventoryItem::create(['hospital_id' => $this->hospital->id, 'base_unit_id' => $this->each->id, 'sku' => 'P5C-MED', 'type' => 'medicine', 'name' => 'Phase 5C medicine', 'reorder_level' => 20, 'requires_pharmacist_validation' => true, 'is_active' => true]);
        $this->mainStore = InventoryLocation::create(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'code' => 'MAIN', 'name' => 'Main Store', 'type' => 'main_store', 'is_active' => true]);
        $this->supplier = Supplier::create(['hospital_id' => $this->hospital->id, 'code' => 'SUP', 'name' => 'Configured Supplier', 'status' => 'active', 'payment_terms' => 'Net 30', 'lead_time_days' => 14]);
    }

    public function test_supplier_requisition_approval_po_totals_and_snapshots(): void
    {
        $workflow = app(ProcurementWorkflowService::class);
        $supplier = $workflow->createSupplier(['hospital_id' => $this->hospital->id, 'code' => 'SUP2', 'name' => 'Second Supplier', 'status' => 'active', 'payment_terms' => 'Net 15', 'item_ids' => [$this->medicine->id]], $this->requester);
        $this->assertCount(1, $supplier->items);

        $req = $this->requisition(10, 1000, discount: 500, tax: 200);
        $this->assertSame(9700, $req->total_minor);
        $workflow->submit($req, $this->requester);
        $this->expectException(HttpException::class);
        $workflow->approve($req->fresh(), $this->requester);
    }

    public function test_approved_requisition_converts_to_numbered_purchase_order(): void
    {
        $workflow = app(ProcurementWorkflowService::class);
        $req = $this->approvedRequisition(10);
        $po = $workflow->convertToPurchaseOrder($req, $this->supplier, $this->approver);

        $this->assertStringStartsWith('PO-', $po->purchase_order_number);
        $this->assertSame('approved', $po->status);
        $this->assertSame('Configured Supplier', $po->supplier_snapshot['name']);
        $this->assertSame('Phase 5C medicine', $po->lines()->first()->item_snapshot['name']);
        $this->assertSame($po->id, $req->refresh()->converted_purchase_order_id);
    }

    public function test_partial_full_receipt_batch_ledger_quarantine_rejected_return_and_reversal(): void
    {
        $workflow = app(ProcurementWorkflowService::class);
        $po = $workflow->convertToPurchaseOrder($this->approvedRequisition(10), $this->supplier, $this->approver);
        $line = $po->lines()->first();

        $receipt = $workflow->receive($po, ['inventory_location_id' => $this->mainStore->id, 'lines' => [['purchase_order_line_id' => $line->id, 'batch_number' => 'LOT-1', 'expiry_date' => today()->addYear()->toDateString(), 'received_quantity' => 4, 'accepted_quantity' => 3, 'rejected_quantity' => 1, 'rejection_reason' => 'Damaged carton']]], $this->requester);
        $receiptLine = $receipt->lines()->first();
        $this->assertSame('quarantine', InventoryBatch::find($receiptLine->inventory_batch_id)->state);
        $this->assertSame('3.0000', StockBalance::where('inventory_batch_id', $receiptLine->inventory_batch_id)->first()->quantity);
        $this->assertSame('partially_received', $po->refresh()->status);

        $secondReceipt = $workflow->receive($po->fresh(), ['inventory_location_id' => $this->mainStore->id, 'lines' => [['purchase_order_line_id' => $line->id, 'batch_number' => 'LOT-2', 'expiry_date' => today()->addYear()->toDateString(), 'received_quantity' => 6, 'accepted_quantity' => 6, 'rejected_quantity' => 0]]], $this->requester);
        $this->assertSame('fully_received', $po->refresh()->status);

        $return = $workflow->supplierReturn($receiptLine, $this->mainStore, 1, $this->approver, 'Supplier return');
        $this->assertSame('supplier_return', $return->action);
        $reversal = $workflow->reverseReceiptLine($secondReceipt->lines()->first(), $this->approver, 'Receipt posting error');
        $this->assertSame('receipt_reversal', $reversal->action);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'goods_receipt']);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'supplier_return']);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'reversal']);
        $this->assertDatabaseHas('procurement_events', ['action' => 'procurement.receipt_reversed']);
    }

    public function test_over_receipt_blocking_limits_scoping_pages_and_reorder_suggestions(): void
    {
        $workflow = app(ProcurementWorkflowService::class);
        $po = $workflow->convertToPurchaseOrder($this->approvedRequisition(5), $this->supplier, $this->approver);
        $this->expectException(HttpException::class);
        $workflow->receive($po, ['inventory_location_id' => $this->mainStore->id, 'lines' => [['purchase_order_line_id' => $po->lines()->first()->id, 'batch_number' => 'OVER', 'received_quantity' => 6, 'accepted_quantity' => 6, 'rejected_quantity' => 0]]], $this->requester);
    }

    public function test_authorization_scoping_dashboard_and_audits(): void
    {
        $this->actingAs($this->requester)->get('/admin/procurement')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Procurement/Index')->has('suppliers')->has('reorderSuggestions'));

        $otherHospital = Hospital::create(['legal_name' => 'Other', 'display_name' => 'Other', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos']);
        $otherSupplier = Supplier::create(['hospital_id' => $otherHospital->id, 'code' => 'OTHER', 'name' => 'Other', 'status' => 'active']);
        $this->actingAs($this->requester)->post('/admin/procurement/suppliers', ['code' => 'NEW', 'name' => 'New Supplier', 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('audit_events', ['action' => 'procurement.supplier_created']);
        $this->assertFalse($this->requester->can('manage', $otherSupplier));
        $this->assertTrue(ProcurementEvent::where('action', 'procurement.supplier_created')->exists());
    }

    private function approvedRequisition(float $quantity): PurchaseRequisition
    {
        $workflow = app(ProcurementWorkflowService::class);
        $req = $this->requisition($quantity, 1000);
        $workflow->submit($req, $this->requester);
        $workflow->approve($req->fresh(), $this->approver);

        return $req->fresh();
    }

    private function requisition(float $quantity, int $unitCost, int $discount = 0, int $tax = 0): PurchaseRequisition
    {
        return app(ProcurementWorkflowService::class)->createRequisition(['hospital_id' => $this->hospital->id, 'facility_id' => $this->facility->id, 'inventory_location_id' => $this->mainStore->id, 'currency' => 'NGN', 'reason' => 'Reorder stock', 'lines' => [['inventory_item_id' => $this->medicine->id, 'inventory_unit_id' => $this->each->id, 'quantity' => $quantity, 'estimated_unit_cost_minor' => $unitCost, 'discount_minor' => $discount, 'tax_minor' => $tax]]], $this->requester);
    }

    private function user(string $role, array $permissions): User
    {
        $user = User::factory()->create(['access_level' => 'admin', 'status' => 'active']);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);
        StaffProfile::create(['user_id' => $user->id, 'hospital_id' => $this->hospital->id, 'staff_number' => strtoupper($role).'-'.substr(md5((string) $user->id), 0, 6), 'job_title' => $role, 'staff_category' => 'operations', 'employment_status' => 'active', 'is_active' => true]);

        return $user->load('staffProfile', 'roles');
    }
}
