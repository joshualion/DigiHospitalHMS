<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('facility_title')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_profile_id', 'facility_id']);
            $table->index(['facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_memberships');
    }
};
