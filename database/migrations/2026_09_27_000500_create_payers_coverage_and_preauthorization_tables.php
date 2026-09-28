<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payer_organizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('type', 64)->index();
            $table->string('code', 100);
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('credit_days')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('payer_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_organization_id')->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name');
            $table->string('currency', 3)->default('NGN');
            $table->boolean('requires_pre_authorization')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payer_organization_id', 'code']);
        });

        Schema::create('payer_plan_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billable_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['payer_plan_id', 'billable_service_id', 'facility_id'], 'payer_tariff_lookup');
        });

        Schema::create('patient_coverages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_plan_id')->constrained()->restrictOnDelete();
            $table->string('member_number', 191);
            $table->string('policy_number')->nullable();
            $table->string('principal_member_name')->nullable();
            $table->string('relationship_to_principal')->nullable();
            $table->string('employer_name')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['hospital_id', 'patient_id', 'status']);
            $table->unique(['payer_plan_id', 'member_number']);
        });

        Schema::create('payer_pre_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_coverage_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('billable_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 120)->nullable();
            $table->string('authorization_code', 120)->nullable();
            $table->string('status', 32)->default('requested')->index();
            $table->bigInteger('requested_amount_minor')->nullable();
            $table->bigInteger('approved_amount_minor')->nullable();
            $table->text('clinical_or_service_context')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->index(['hospital_id', 'patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payer_pre_authorizations');
        Schema::dropIfExists('patient_coverages');
        Schema::dropIfExists('payer_plan_tariffs');
        Schema::dropIfExists('payer_plans');
        Schema::dropIfExists('payer_organizations');
    }
};
