<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blood_transfusion_episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_component_issue_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('blood_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('stopped_at')->nullable();
            $table->string('status')->default('in_progress')->index();
            $table->string('destination')->nullable();
            $table->string('patient_identifier_checked');
            $table->string('component_identifier_checked');
            $table->string('identity_check_status')->default('matched');
            $table->text('stop_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'patient_id', 'status']);
        });

        Schema::create('blood_transfusion_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blood_transfusion_episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('observed_at');
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('pulse_bpm')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('spo2_percent')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['blood_transfusion_episode_id', 'observed_at']);
        });

        Schema::create('blood_transfusion_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blood_transfusion_episode_id')->constrained()->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('occurred_at');
            $table->string('reported_severity')->nullable();
            $table->text('observed_signs');
            $table->text('immediate_actions')->nullable();
            $table->dateTime('clinician_notified_at')->nullable();
            $table->dateTime('blood_bank_notified_at')->nullable();
            $table->string('status')->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['blood_transfusion_episode_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_transfusion_reactions');
        Schema::dropIfExists('blood_transfusion_observations');
        Schema::dropIfExists('blood_transfusion_episodes');
    }
};
