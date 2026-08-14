<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->uuid('request_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['hospital_id', 'action', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
