<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_site_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('alt_text');
            $table->text('caption')->nullable();
            $table->string('credit')->nullable();
            $table->string('disk')->default('public');
            $table->string('path')->unique();
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('focal_point')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hospital_id', 'mime_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_site_media');
    }
};
