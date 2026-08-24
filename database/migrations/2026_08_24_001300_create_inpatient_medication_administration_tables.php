<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'medication_order_type' => fn (Blueprint $table) => $table->string('medication_order_type')->default('regular')->after('is_prn'),
            'scheduled_times' => fn (Blueprint $table) => $table->json('scheduled_times')->nullable()->after('medication_order_type'),
            'start_at' => fn (Blueprint $table) => $table->timestamp('start_at')->nullable()->after('scheduled_times'),
            'end_at' => fn (Blueprint $table) => $table->timestamp('end_at')->nullable()->after('start_at'),
        ] as $column => $definition) {
            if (! Schema::hasColumn('prescription_items', $column)) {
                Schema::table('prescription_items', $definition);
            }
        }

        Schema::create('emar_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $table->string('medicine_name');
            $table->string('dose');
            $table->string('route')->nullable();
            $table->string('frequency')->nullable();
            $table->string('order_type')->default('regular');
            $table->boolean('is_prn')->default(false);
            $table->text('prn_instructions')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['prescription_item_id', 'scheduled_at']);
        });

        Schema::create('emar_administrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('emar_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_dispense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medicine_name');
            $table->string('dose');
            $table->string('route')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->dateTime('actual_at');
            $table->decimal('quantity_administered', 12, 4)->default(1);
            $table->string('outcome');
            $table->json('confirmation');
            $table->text('reason')->nullable();
            $table->text('prn_indication')->nullable();
            $table->text('prn_response')->nullable();
            $table->foreignId('administered_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique('emar_schedule_id');
        });

        Schema::create('emar_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('emar_administration_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->text('content');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authored_at');
            $table->timestamps();
        });

        Schema::create('emar_events', function (Blueprint $table): void {
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
        Schema::dropIfExists('emar_events');
        Schema::dropIfExists('emar_amendments');
        Schema::dropIfExists('emar_administrations');
        Schema::dropIfExists('emar_schedules');
        foreach (['medication_order_type', 'scheduled_times', 'start_at', 'end_at'] as $column) {
            if (Schema::hasColumn('prescription_items', $column)) {
                Schema::table('prescription_items', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
