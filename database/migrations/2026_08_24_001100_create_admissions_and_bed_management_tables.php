<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bed_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('billable_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('wards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'facility_id', 'code']);
        });

        Schema::create('ward_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['ward_id', 'code']);
        });

        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_id')->constrained()->restrictOnDelete();
            $table->foreignId('ward_room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bed_class_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('state')->default('available');
            $table->text('state_reason')->nullable();
            $table->timestamps();
            $table->unique(['ward_id', 'code']);
        });

        Schema::create('admissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requesting_clinician_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('attending_clinician_id')->nullable()->constrained('staff_profiles')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('current_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admission_number')->nullable()->unique();
            $table->string('status')->default('requested');
            $table->text('reason')->nullable();
            $table->text('provisional_diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('admitted_at')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->string('discharge_destination')->nullable();
            $table->string('discharge_outcome')->nullable();
            $table->text('discharge_notes')->nullable();
            $table->boolean('administrative_clearance_required')->default(false);
            $table->boolean('administrative_clearance_resolved')->default(true);
            $table->boolean('discharge_override_used')->default(false);
            $table->text('status_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_bed_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('to_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('from_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('to_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('from_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('to_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->string('movement_type');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_events', function (Blueprint $table): void {
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
        foreach (['admission_events', 'admission_bed_movements', 'admissions', 'beds', 'ward_rooms', 'wards', 'bed_classes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
