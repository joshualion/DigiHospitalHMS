<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_comment_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_comment_id')->constrained('blog_comments')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 20);
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['blog_comment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comment_reviews');
    }
};
