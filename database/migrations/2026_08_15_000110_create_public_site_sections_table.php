<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_site_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_site_page_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('type');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('draft_content')->nullable();
            $table->json('published_content')->nullable();
            $table->unsignedInteger('published_version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['public_site_page_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_site_sections');
    }
};
