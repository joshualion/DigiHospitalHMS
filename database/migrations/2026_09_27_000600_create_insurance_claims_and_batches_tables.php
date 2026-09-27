<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_coverage_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_plan_id')->constrained()->restrictOnDelete();
            $table->string('claim_number');
            $table->unsignedInteger('submission_round')->default(1);
            $table->string('status')->default('draft')->index();
            $table->string('currency', 3);
            $table->bigInteger('claimed_minor')->default(0);
            $table->bigInteger('approved_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('outstanding_minor')->default(0);
            $table->string('external_reference')->nullable();
            $table->text('submission_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('last_resubmitted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['hospital_id','claim_number']);
            $table->index(['hospital_id','payer_organization_id','status']);
        });

        Schema::create('insurance_claim_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billable_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_code')->nullable();
            $table->string('service_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('claimed_minor');
            $table->bigInteger('approved_minor')->default(0);
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_claim_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('payload')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('insurance_claim_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_organization_id')->constrained()->restrictOnDelete();
            $table->string('batch_number');
            $table->string('status')->default('draft')->index();
            $table->string('currency',3);
            $table->unsignedInteger('claim_count')->default(0);
            $table->bigInteger('total_claimed_minor')->default(0);
            $table->string('external_reference')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id','batch_number']);
        });

        Schema::create('insurance_claim_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_claim_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_claim_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique('insurance_claim_id');
        });

        Schema::create('insurance_claim_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_claim_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('reference')->nullable();
            $table->date('received_on');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_payments');
        Schema::dropIfExists('insurance_claim_batch_items');
        Schema::dropIfExists('insurance_claim_batches');
        Schema::dropIfExists('insurance_claim_events');
        Schema::dropIfExists('insurance_claim_lines');
        Schema::dropIfExists('insurance_claims');
    }
};
