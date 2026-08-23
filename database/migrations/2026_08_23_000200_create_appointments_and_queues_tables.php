<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('clinician_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->json('breaks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hospital_id', 'facility_id', 'staff_profile_id', 'day_of_week', 'is_active'], 'clinician_schedule_lookup');
        });

        Schema::create('clinician_unavailabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'staff_profile_id', 'starts_at', 'ends_at'], 'clinician_unavailable_lookup');
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinician_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->foreignId('appointment_type_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('scheduled')->index();
            $table->string('source')->default('staff');
            $table->text('reason')->nullable();
            $table->foreignId('booked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'facility_id', 'starts_at']);
            $table->index(['hospital_id', 'clinician_id', 'starts_at', 'ends_at'], 'appointment_conflict_lookup');
        });

        Schema::create('appointment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
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

        Schema::create('public_appointment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('preferred_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('preferred_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->text('phone_encrypted')->nullable();
            $table->string('phone_hash', 64)->nullable()->index();
            $table->text('email_encrypted')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->date('preferred_date')->nullable();
            $table->boolean('consent')->default(false);
            $table->string('status')->default('pending')->index();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinician_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source');
            $table->string('status')->default('checked_in')->index();
            $table->foreignId('checked_in_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamps();
        });

        Schema::create('queue_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinician_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('queue_number');
            $table->unsignedSmallInteger('priority')->default(3);
            $table->string('status')->default('waiting')->index();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['facility_id', 'queue_date', 'queue_number']);
            $table->index(['hospital_id', 'facility_id', 'department_id', 'queue_date', 'status'], 'queue_board_lookup');
        });

        Schema::create('queue_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('queue_entry_id')->constrained()->cascadeOnDelete();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_events');
        Schema::dropIfExists('queue_entries');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('public_appointment_requests');
        Schema::dropIfExists('appointment_events');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('clinician_unavailabilities');
        Schema::dropIfExists('clinician_schedules');
        Schema::dropIfExists('appointment_types');
    }
};
