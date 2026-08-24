<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('inventory_item_supplier', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_item_code')->nullable();
            $table->unsignedBigInteger('last_unit_cost_minor')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();
            $table->unique(['inventory_item_id', 'supplier_id']);
        });

        Schema::create('procurement_approval_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('role_name');
            $table->unsignedBigInteger('limit_minor');
            $table->string('currency', 3)->default('NGN');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'role_name', 'currency'], 'proc_approval_role_currency_unique');
        });

        Schema::create('purchase_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->unsignedBigInteger('converted_purchase_order_id')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->unsignedBigInteger('estimated_unit_cost_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchase_order_number')->unique();
            $table->string('status')->default('approved');
            $table->string('currency', 3)->default('NGN');
            $table->json('supplier_snapshot');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_requisition_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->json('item_snapshot');
            $table->decimal('quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->unsignedBigInteger('unit_cost_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->string('grn_number')->unique();
            $table->string('status')->default('posted');
            $table->string('delivery_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number');
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->unsignedBigInteger('unit_cost_minor');
            $table->string('batch_state')->default('available');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('action')->default('supplier_return');
            $table->text('reason');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();
        });

        Schema::create('procurement_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['procurement_events', 'supplier_returns', 'goods_receipt_lines', 'goods_receipts', 'purchase_order_lines', 'purchase_orders', 'purchase_requisition_lines', 'purchase_requisitions', 'procurement_approval_limits', 'inventory_item_supplier', 'suppliers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
