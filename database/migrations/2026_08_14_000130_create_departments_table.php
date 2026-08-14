<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('category')->default('administrative');
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['hospital_id', 'code']);
            $table->index(['hospital_id', 'facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
