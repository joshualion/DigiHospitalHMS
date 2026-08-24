<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('main_store');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('inventory_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->decimal('base_factor', 18, 6)->default(1);
            $table->foreignId('base_unit_id')->nullable()->constrained('inventory_units')->nullOnDelete();
            $table->boolean('requires_pharmacist_validation')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('type')->default('medicine');
            $table->string('generic_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('name');
            $table->string('dosage_form')->nullable();
            $table->string('strength')->nullable();
            $table->string('route')->nullable();
            $table->text('description')->nullable();
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->boolean('requires_pharmacist_validation')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'sku']);
            $table->unique(['hospital_id', 'barcode']);
        });

        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('batch_number');
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('supplier_reference')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->unsignedBigInteger('unit_cost_minor')->nullable();
            $table->string('state')->default('quarantine');
            $table->timestamps();
            $table->unique(['hospital_id', 'inventory_item_id', 'batch_number'], 'inv_batch_hosp_item_lot_unique');
            $table->index(['hospital_id', 'state', 'expiry_date'], 'inv_batches_state_exp_idx');
        });

        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();
            $table->unique(['hospital_id', 'inventory_location_id', 'inventory_item_id', 'inventory_batch_id'], 'stock_balance_scope_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->foreignId('inventory_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->string('movement_type');
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->unsignedBigInteger('unit_cost_minor')->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('reverses_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index(['hospital_id', 'inventory_item_id', 'inventory_batch_id'], 'stock_move_item_batch_idx');
        });

        Schema::create('inventory_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('inventory_locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('status')->default('requested');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_delta', 18, 4);
            $table->string('status')->default('requested');
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_events', function (Blueprint $table): void {
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
        foreach (['inventory_events', 'inventory_adjustment_requests', 'inventory_transfer_requests', 'stock_movements', 'stock_balances', 'inventory_batches', 'inventory_items', 'inventory_units', 'inventory_locations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
