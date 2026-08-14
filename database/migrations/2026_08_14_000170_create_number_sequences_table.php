<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('prefix')->nullable();
            $table->string('date_format')->nullable();
            $table->unsignedTinyInteger('padding_length')->default(6);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedBigInteger('issued_count')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['hospital_id', 'facility_id', 'key']);
            $table->index(['hospital_id', 'key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
