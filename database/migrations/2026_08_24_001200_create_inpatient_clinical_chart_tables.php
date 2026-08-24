<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inpatient_charts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->string('status')->default('active');
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inpatient_progress_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('note_type')->default('soap');
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('narrative')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authored_at');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inpatient_nursing_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('shift')->nullable();
            $table->text('note');
            $table->string('status')->default('signed');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authored_at');
            $table->timestamps();
        });

        Schema::create('inpatient_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->string('temperature_unit', 5)->default('C');
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->unsignedSmallInteger('oxygen_saturation')->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->decimal('glucose', 8, 2)->nullable();
            $table->string('glucose_unit')->nullable();
            $table->text('consciousness_notes')->nullable();
            $table->timestamp('observed_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('inpatient_intake_outputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('direction');
            $table->string('measurement_type');
            $table->decimal('quantity', 10, 2);
            $table->string('unit');
            $table->text('notes')->nullable();
            $table->timestamp('measured_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('inpatient_care_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->text('problem');
            $table->text('goal')->nullable();
            $table->text('intervention')->nullable();
            $table->text('evaluation')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('inpatient_diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->string('coding_system')->nullable();
            $table->string('code')->nullable();
            $table->string('status')->default('provisional');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('inpatient_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('order_type');
            $table->text('instruction');
            $table->string('status')->default('draft');
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('ordered_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('inpatient_handover_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('from_shift');
            $table->string('to_shift');
            $table->text('summary');
            $table->string('status')->default('signed');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authored_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inpatient_discharge_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->text('admission_summary')->nullable();
            $table->text('diagnosis_summary')->nullable();
            $table->text('results_summary')->nullable();
            $table->text('clinical_course')->nullable();
            $table->text('discharge_plan')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('drafted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('drafted_at');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inpatient_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('amendable');
            $table->text('reason');
            $table->text('content');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authored_at');
            $table->timestamps();
        });

        Schema::create('inpatient_chart_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('inpatient_chart_id')->nullable()->constrained()->nullOnDelete();
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
        foreach (['inpatient_chart_events', 'inpatient_amendments', 'inpatient_discharge_summaries', 'inpatient_handover_records', 'inpatient_orders', 'inpatient_diagnoses', 'inpatient_care_plans', 'inpatient_intake_outputs', 'inpatient_observations', 'inpatient_nursing_notes', 'inpatient_progress_notes', 'inpatient_charts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
