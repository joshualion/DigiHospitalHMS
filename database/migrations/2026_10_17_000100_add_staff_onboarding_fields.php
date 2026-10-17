<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('password_setup_at')->nullable();
            $table->timestamp('invitation_failed_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('staff_profiles', fn (Blueprint $table) => $table->dropColumn([
            'invitation_sent_at', 'password_setup_at', 'invitation_failed_at',
        ]));
    }
};
