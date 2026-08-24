<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blood_bank_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('blood_bank');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('blood_storage_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_bank_location_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('storage_type')->default('refrigerator');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['blood_bank_location_id', 'code']);
        });

        Schema::create('blood_donor_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('blood_donors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donor_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->string('donor_number')->unique();
            $table->string('status')->default('active');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('sex')->nullable();
            $table->text('address_encrypted')->nullable();
            $table->string('phone_encrypted')->nullable();
            $table->string('phone_hash')->nullable()->index();
            $table->string('email_encrypted')->nullable();
            $table->string('email_hash')->nullable()->index();
            $table->string('identifier_type')->nullable();
            $table->string('identifier_encrypted')->nullable();
            $table->string('identifier_hash')->nullable()->index();
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['hospital_id', 'status']);
        });

        Schema::create('blood_donor_screenings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->json('responses')->nullable();
            $table->string('eligibility_status')->default('pending');
            $table->text('decision_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blood_donor_deferrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('deferral_type')->default('manual');
            $table->text('reason');
            $table->date('deferred_until')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();
        });

        Schema::create('blood_donation_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_bank_location_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('blood_donations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donor_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donation_appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('blood_bank_location_id')->constrained()->restrictOnDelete();
            $table->string('donation_number')->unique();
            $table->string('collection_number')->unique();
            $table->dateTime('collected_at');
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->string('bag_type');
            $table->unsignedInteger('volume_ml')->nullable();
            $table->string('status')->default('collected');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['hospital_id', 'status']);
        });

        Schema::create('blood_group_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donation_id')->constrained()->restrictOnDelete();
            $table->string('abo_group')->nullable();
            $table->string('rh_factor')->nullable();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('entered_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blood_screening_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_test_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_required_for_release')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('blood_screening_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donation_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_screening_test_id')->constrained()->restrictOnDelete();
            $table->foreignId('lab_specimen_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lab_result_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result_value')->nullable();
            $table->boolean('release_cleared')->default(false);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('entered_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['blood_donation_id', 'blood_screening_test_id'], 'bb_screening_result_unique');
        });

        Schema::create('blood_component_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('default_shelf_life_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('blood_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_donation_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_component_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_bank_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_storage_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component_number')->unique();
            $table->string('abo_group')->nullable();
            $table->string('rh_factor')->nullable();
            $table->unsignedInteger('volume_ml')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('state')->default('quarantined');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('prepared_at');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['hospital_id', 'state']);
            $table->index(['abo_group', 'rh_factor']);
        });

        Schema::create('blood_component_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('blood_component_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('blood_bank_locations')->nullOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('blood_bank_locations')->nullOnDelete();
            $table->foreignId('from_storage_unit_id')->nullable()->constrained('blood_storage_units')->nullOnDelete();
            $table->foreignId('to_storage_unit_id')->nullable()->constrained('blood_storage_units')->nullOnDelete();
            $table->string('status')->default('completed');
            $table->text('reason');
            $table->foreignId('transferred_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('transferred_at');
            $table->timestamps();
        });

        Schema::create('blood_bank_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->nullableMorphs('subject');
            $table->text('reason');
            $table->text('content');
            $table->foreignId('authored_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('authored_at');
            $table->timestamps();
        });

        Schema::create('blood_bank_events', function (Blueprint $table): void {
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
            $table->dateTime('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_bank_events');
        Schema::dropIfExists('blood_bank_amendments');
        Schema::dropIfExists('blood_component_transfers');
        Schema::dropIfExists('blood_components');
        Schema::dropIfExists('blood_component_types');
        Schema::dropIfExists('blood_screening_results');
        Schema::dropIfExists('blood_screening_tests');
        Schema::dropIfExists('blood_group_results');
        Schema::dropIfExists('blood_donations');
        Schema::dropIfExists('blood_donation_appointments');
        Schema::dropIfExists('blood_donor_deferrals');
        Schema::dropIfExists('blood_donor_screenings');
        Schema::dropIfExists('blood_donors');
        Schema::dropIfExists('blood_donor_categories');
        Schema::dropIfExists('blood_storage_units');
        Schema::dropIfExists('blood_bank_locations');
    }
};
