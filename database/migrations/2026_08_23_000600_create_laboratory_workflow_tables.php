<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_specimen_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('collection_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('lab_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('lab_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_specimen_type_id')->nullable()->constrained('lab_specimen_types')->nullOnDelete();
            $table->foreignId('billable_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('turnaround_time')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('lab_test_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('result_type')->default('numeric');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['lab_test_id', 'code']);
        });

        Schema::create('lab_test_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('lab_test_profile_test', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lab_test_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['lab_test_profile_id', 'lab_test_id']);
        });

        Schema::create('lab_reference_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_test_component_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Default');
            $table->decimal('low_value', 16, 4)->nullable();
            $table->decimal('high_value', 16, 4)->nullable();
            $table->decimal('critical_low_value', 16, 4)->nullable();
            $table->decimal('critical_high_value', 16, 4)->nullable();
            $table->string('qualitative_normal')->nullable();
            $table->text('display_text')->nullable();
            $table->json('criteria')->nullable();
            $table->boolean('requires_professional_validation')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lab_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordering_clinician_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->string('request_number')->unique();
            $table->string('accession_number')->unique();
            $table->string('status')->default('ordered')->index();
            $table->string('priority')->default('routine');
            $table->text('clinical_notes')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('ordered_at');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['hospital_id', 'facility_id', 'status', 'ordered_at']);
        });

        Schema::create('lab_request_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('test_code');
            $table->string('test_name');
            $table->string('status')->default('ordered')->index();
            $table->json('component_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_specimens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_specimen_type_id')->constrained()->restrictOnDelete();
            $table->string('label_number')->unique();
            $table->string('status')->default('pending')->index();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_request_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_component_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component_code');
            $table->string('component_name');
            $table->string('result_type');
            $table->decimal('numeric_value', 16, 4)->nullable();
            $table->text('text_value')->nullable();
            $table->string('qualitative_value')->nullable();
            $table->text('comment')->nullable();
            $table->json('reference_range_snapshot')->nullable();
            $table->string('flag')->default('normal');
            $table->boolean('is_critical')->default(false);
            $table->string('status')->default('draft')->index();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('entered_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('critical_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('critical_acknowledged_at')->nullable();
            $table->text('critical_escalation_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_report_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->text('content');
            $table->timestamp('authored_at');
            $table->timestamps();
        });

        Schema::create('lab_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_events');
        Schema::dropIfExists('lab_report_amendments');
        Schema::dropIfExists('lab_results');
        Schema::dropIfExists('lab_specimens');
        Schema::dropIfExists('lab_request_tests');
        Schema::dropIfExists('lab_requests');
        Schema::dropIfExists('lab_reference_ranges');
        Schema::dropIfExists('lab_test_profile_test');
        Schema::dropIfExists('lab_test_profiles');
        Schema::dropIfExists('lab_test_components');
        Schema::dropIfExists('lab_tests');
        Schema::dropIfExists('lab_units');
        Schema::dropIfExists('lab_specimen_types');
    }
};
