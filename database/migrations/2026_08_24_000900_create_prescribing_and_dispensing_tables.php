<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->foreignId('billable_service_id')->nullable()->after('base_unit_id')->constrained()->nullOnDelete();
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescribing_clinician_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prescription_number')->unique();
            $table->string('status')->default('draft');
            $table->text('clinical_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medicine_name');
            $table->string('dose');
            $table->string('route')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('dispensed_quantity', 18, 4)->default(0);
            $table->text('instructions')->nullable();
            $table->text('indication')->nullable();
            $table->boolean('is_prn')->default(false);
            $table->text('prn_instructions')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('prescription_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->foreignId('substituted_inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->text('substitution_note')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('prescription_dispenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('action')->default('dispense');
            $table->text('instructions')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();
        });

        Schema::create('prescription_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->text('content');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authored_at');
            $table->timestamps();
        });

        Schema::create('prescription_events', function (Blueprint $table): void {
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
        foreach (['prescription_events', 'prescription_amendments', 'prescription_dispenses', 'prescription_reviews', 'prescription_items', 'prescriptions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('billable_service_id');
        });
    }
};
