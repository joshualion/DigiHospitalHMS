<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_dispenses', function (Blueprint $table): void {
            $table->foreignId('source_dispense_id')->nullable()->after('stock_movement_id')->constrained('prescription_dispenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescription_dispenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_dispense_id');
        });
    }
};
