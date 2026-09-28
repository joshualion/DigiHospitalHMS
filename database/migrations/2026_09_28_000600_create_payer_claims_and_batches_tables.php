<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payer_claim_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_organization_id')->constrained()->restrictOnDelete();
            $table->string('reference', 120);
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 32)->default('draft')->index();
            $table->bigInteger('claimed_minor')->default(0);
            $table->bigInteger('approved_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->date('due_date')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'reference']);
        });

        Schema::create('payer_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_claim_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_coverage_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_pre_authorization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resubmission_of_claim_id')->nullable()->constrained('payer_claims')->nullOnDelete();
            $table->string('claim_number', 120);
            $table->string('payer_reference', 120)->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('currency', 3)->default('NGN');
            $table->bigInteger('claimed_minor');
            $table->bigInteger('approved_minor')->nullable();
            $table->bigInteger('paid_minor')->default(0);
            $table->date('service_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->text('submission_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id', 'claim_number']);
            $table->index(['hospital_id', 'payer_organization_id', 'status'], 'payer_claim_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payer_claims');
        Schema::dropIfExists('payer_claim_batches');
    }
};
