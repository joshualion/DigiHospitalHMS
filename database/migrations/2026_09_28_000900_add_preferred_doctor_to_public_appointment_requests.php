<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_appointment_requests', function (Blueprint $table): void {
            $table->foreignId('preferred_clinician_id')
                ->nullable()
                ->after('preferred_department_id')
                ->constrained('staff_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_appointment_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('preferred_clinician_id');
        });
    }
};
