<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('access_level')->index();
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['status', 'suspended_at']);
        });
    }
};
