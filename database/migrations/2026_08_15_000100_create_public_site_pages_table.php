<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_site_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->string('template')->default('standard');
            $table->string('status')->default('draft')->index();
            $table->json('draft_content')->nullable();
            $table->json('published_content')->nullable();
            $table->json('seo')->nullable();
            $table->unsignedInteger('published_version')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unpublished_at')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'slug']);
            $table->index(['hospital_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_site_pages');
    }
};
