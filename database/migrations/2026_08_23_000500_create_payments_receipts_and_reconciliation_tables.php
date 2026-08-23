<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->bigInteger('paid_minor')->default(0)->after('total_minor');
            $table->bigInteger('balance_minor')->default(0)->after('paid_minor');
            $table->string('payment_status')->default('unpaid')->after('balance_minor')->index();
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->json('reference_fields')->nullable();
            $table->boolean('requires_open_shift')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
        });

        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('opening_float_minor')->default(0);
            $table->bigInteger('cash_collections_minor')->default(0);
            $table->bigInteger('expected_cash_minor')->default(0);
            $table->bigInteger('counted_cash_minor')->nullable();
            $table->bigInteger('variance_minor')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'facility_id', 'cashier_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('unallocated_minor')->default(0);
            $table->bigInteger('refunded_minor')->default(0);
            $table->string('status')->default('posted')->index();
            $table->string('idempotency_key')->nullable();
            $table->json('reference_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'idempotency_key']);
            $table->index(['hospital_id', 'patient_id', 'status']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('status')->default('posted')->index();
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'invoice_id', 'status']);
        });

        Schema::create('refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->string('status')->default('requested')->index();
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table): void {
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
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('payment_methods');
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['paid_minor', 'balance_minor', 'payment_status']);
        });
    }
};
