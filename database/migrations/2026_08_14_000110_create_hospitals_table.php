<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table): void {
            $table->id();
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('registration_reference')->nullable();
            $table->string('email')->nullable();
            $table->json('phone_numbers')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Nigeria');
            $table->string('postal_code')->nullable();
            $table->string('timezone')->default('Africa/Lagos');
            $table->string('logo_path')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->string('primary_contact_phone')->nullable();
            $table->string('default_currency', 3)->default('NGN');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
