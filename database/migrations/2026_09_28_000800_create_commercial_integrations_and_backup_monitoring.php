<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_licenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->unique()->constrained()->restrictOnDelete();
            $table->string('license_key', 191)->nullable();
            $table->string('plan', 64)->default('standard');
            $table->string('status', 32)->default('trial')->index();
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable()->index();
            $table->unsignedInteger('licensed_facilities')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('type', 64)->index();
            $table->string('provider', 100);
            $table->string('status', 32)->default('disabled')->index();
            $table->json('configuration')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status', 32)->nullable();
            $table->text('last_check_message')->nullable();
            $table->timestamps();
            $table->unique(['hospital_id','type']);
        });

        Schema::create('backup_monitor_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->index();
            $table->string('source', 64)->default('manual');
            $table->string('backup_reference', 191)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->timestamp('backup_completed_at')->nullable()->index();
            $table->timestamp('restore_verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_monitor_events');
        Schema::dropIfExists('integration_configurations');
        Schema::dropIfExists('installation_licenses');
    }
};
