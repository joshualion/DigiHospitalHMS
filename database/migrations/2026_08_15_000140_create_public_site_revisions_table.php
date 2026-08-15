<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_site_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->morphs('revisionable');
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->string('action')->default('publish')->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['revisionable_type', 'revisionable_id', 'version'], 'ps_revisions_subject_version_unique');
            $table->index(['hospital_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_site_revisions');
    }
};
