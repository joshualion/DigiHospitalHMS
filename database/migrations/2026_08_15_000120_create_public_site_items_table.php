<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_site_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('public_site_section_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('presentable');
            $table->string('type')->index();
            $table->string('slug')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('draft_content')->nullable();
            $table->json('published_content')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('published_version')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hospital_id', 'type', 'slug']);
            $table->index(['hospital_id', 'type', 'status', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_site_items');
    }
};
