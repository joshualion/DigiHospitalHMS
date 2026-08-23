<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_clinician_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->string('source')->default('outpatient');
            $table->string('status')->default('in_progress')->index();
            $table->text('presenting_complaint')->nullable();
            $table->text('history_presenting_complaint')->nullable();
            $table->text('medical_history')->nullable();
            $table->text('surgical_history')->nullable();
            $table->text('medication_history')->nullable();
            $table->text('family_history')->nullable();
            $table->text('social_history')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('referral_recommendation')->nullable();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->foreignId('paused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paused_at')->nullable();
            $table->foreignId('resumed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resumed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->unique(['visit_id', 'status'], 'one_active_encounter_per_visit');
            $table->index(['hospital_id', 'facility_id', 'status']);
            $table->index(['hospital_id', 'responsible_clinician_id', 'status'], 'clinician_encounter_worklist');
        });

        Schema::create('clinical_encounter_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
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

        Schema::create('encounter_vitals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->string('temperature_unit', 1)->default('C');
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->unsignedSmallInteger('oxygen_saturation')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->timestamp('measured_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'patient_id', 'measured_at']);
        });

        Schema::create('encounter_diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->text('description');
            $table->string('coding_system')->nullable();
            $table->string('code')->nullable();
            $table->string('status')->default('provisional');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('encounter_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->text('content');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('authored_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounter_amendments');
        Schema::dropIfExists('encounter_diagnoses');
        Schema::dropIfExists('encounter_vitals');
        Schema::dropIfExists('clinical_encounter_events');
        Schema::dropIfExists('clinical_encounters');
    }
};
