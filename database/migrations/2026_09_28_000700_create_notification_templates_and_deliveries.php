<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('key', 100);
            $table->string('channel', 32);
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->unsignedInteger('reminder_minutes_before')->default(1440);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['hospital_id','key','channel']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 32)->index();
            $table->text('recipient_encrypted')->nullable();
            $table->string('recipient_hash',64)->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 32)->default('queued')->index();
            $table->string('provider', 100)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('fingerprint',64);
            $table->timestamps();
            $table->unique(['hospital_id','fingerprint']);
            $table->index(['hospital_id','status','scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
    }
};
