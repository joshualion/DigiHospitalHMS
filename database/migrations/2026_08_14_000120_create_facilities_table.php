<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('facility_type')->default('branch');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Nigeria');
            $table->string('timezone')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->json('opening_hours')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
