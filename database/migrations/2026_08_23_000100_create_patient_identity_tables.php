<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_facility_id')->constrained('facilities')->restrictOnDelete();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->string('hospital_number');
            $table->string('status')->default('active')->index();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('estimated_age_years')->nullable();
            $table->boolean('is_dob_estimated')->default(false);
            $table->string('sex', 20);
            $table->string('marital_status', 40)->nullable();
            $table->string('occupation')->nullable();
            $table->text('address')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('phone_hash', 64)->nullable()->index();
            $table->text('email_encrypted')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deceased_at')->nullable();
            $table->foreignId('deceased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('status_reason')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'hospital_number']);
            $table->index(['hospital_id', 'status']);
            $table->index(['hospital_id', 'last_name', 'first_name']);
        });

        Schema::create('patient_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->text('value_encrypted');
            $table->string('value_hash', 64)->index();
            $table->boolean('is_searchable')->default(true);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'type', 'value_hash']);
        });

        Schema::create('patient_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('type')->default('contact');
            $table->string('name');
            $table->string('relationship')->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('phone_hash', 64)->nullable()->index();
            $table->text('email_encrypted')->nullable();
            $table->string('email_hash', 64)->nullable()->index();
            $table->text('address')->nullable();
            $table->boolean('is_next_of_kin')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'type']);
        });

        Schema::create('patient_allergies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('substance');
            $table->string('reaction')->nullable();
            $table->string('severity')->default('unknown');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'status', 'severity']);
        });

        Schema::create('patient_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('category')->default('general');
            $table->string('severity')->default('medium');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'status', 'severity']);
        });

        Schema::create('patient_activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['hospital_id', 'patient_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_activity_events');
        Schema::dropIfExists('patient_alerts');
        Schema::dropIfExists('patient_allergies');
        Schema::dropIfExists('patient_contacts');
        Schema::dropIfExists('patient_identifiers');
        Schema::dropIfExists('patients');
    }
};
