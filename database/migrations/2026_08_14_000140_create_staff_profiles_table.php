<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('staff_number');
            $table->string('job_title')->nullable();
            $table->string('staff_category')->default('administrative');
            $table->string('professional_license_number')->nullable();
            $table->date('license_expires_at')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('employment_status')->default('active')->index();
            $table->date('hire_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'staff_number']);
            $table->index(['hospital_id', 'employment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
