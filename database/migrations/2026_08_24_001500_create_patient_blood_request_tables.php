<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blood_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requesting_clinician_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->foreignId('blood_component_type_id')->constrained()->restrictOnDelete();
            $table->string('request_number')->unique();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_reserved')->default(0);
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->text('clinical_indication');
            $table->string('priority')->default('routine');
            $table->dateTime('required_at')->nullable();
            $table->string('state')->default('draft');
            $table->boolean('identity_discrepancy_unresolved')->default(false);
            $table->boolean('specimen_label_discrepancy_unresolved')->default(false);
            $table->boolean('blood_group_discrepancy_unresolved')->default(false);
            $table->boolean('emergency_release_authorized')->default(false);
            $table->text('emergency_release_justification')->nullable();
            $table->foreignId('emergency_release_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emergency_release_authorized_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('requested_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();
            $table->index(['hospital_id', 'state']);
            $table->index(['patient_id', 'state']);
        });

        Schema::create('blood_request_specimens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_request_id')->constrained()->restrictOnDelete();
            $table->string('label')->unique();
            $table->dateTime('collected_at');
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->string('collection_location')->nullable();
            $table->string('patient_confirmed_name');
            $table->string('patient_confirmed_identifier');
            $table->string('label_status')->default('matched');
            $table->text('label_discrepancy_notes')->nullable();
            $table->string('status')->default('collected');
            $table->json('custody_chain')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_blood_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_request_specimen_id')->nullable()->constrained()->nullOnDelete();
            $table->string('abo_group');
            $table->string('rh_factor');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('entered_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'status']);
        });

        Schema::create('patient_blood_group_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_blood_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('abo_group')->nullable();
            $table->string('rh_factor')->nullable();
            $table->text('reason');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('authored_at');
            $table->timestamps();
        });

        Schema::create('blood_compatibility_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_request_specimen_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blood_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('test_type')->default('manual_crossmatch');
            $table->string('result');
            $table->text('interpretation')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('entered_at');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();
            $table->index(['blood_request_id', 'status']);
        });

        Schema::create('blood_component_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_component_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->dateTime('reserved_at');
            $table->dateTime('expires_at');
            $table->foreignId('reserved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('release_reason')->nullable();
            $table->timestamps();
            $table->index('blood_component_id', 'bb_component_reservation_component_idx');
            $table->index(['blood_request_id', 'status']);
        });

        Schema::create('blood_component_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_component_reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blood_component_id')->constrained()->restrictOnDelete();
            $table->string('issue_number')->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('received_by_name');
            $table->string('receiver_role')->nullable();
            $table->dateTime('issued_at');
            $table->string('destination');
            $table->string('status')->default('issued');
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('return_reason')->nullable();
            $table->foreignId('return_assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('return_assessed_at')->nullable();
            $table->text('return_assessment')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['blood_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_component_issues');
        Schema::dropIfExists('blood_component_reservations');
        Schema::dropIfExists('blood_compatibility_tests');
        Schema::dropIfExists('patient_blood_group_amendments');
        Schema::dropIfExists('patient_blood_groups');
        Schema::dropIfExists('blood_request_specimens');
        Schema::dropIfExists('blood_requests');
    }
};
