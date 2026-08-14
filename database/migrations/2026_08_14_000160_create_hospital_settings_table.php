<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hospital_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('default_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->string('locale')->default('en');
            $table->string('timezone')->default('Africa/Lagos');
            $table->string('currency', 3)->default('NGN');
            $table->string('date_format')->default('Y-m-d');
            $table->string('time_format')->default('H:i');
            $table->json('branding')->nullable();
            $table->json('contact_details')->nullable();
            $table->json('operating_preferences')->nullable();
            $table->json('public_site_defaults')->nullable();
            $table->json('numbering_preferences')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_settings');
    }
};
