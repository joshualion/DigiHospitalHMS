<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('outbound_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('provider')->default('custom');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption')->default('tls');
            $table->string('username');
            $table->text('password');
            $table->string('from_address');
            $table->string('from_name');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('outbound_mail_settings');
    }
};
