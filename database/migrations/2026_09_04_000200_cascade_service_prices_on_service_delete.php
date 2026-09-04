<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_prices', function (Blueprint $table): void {
            $table->dropForeign(['billable_service_id']);
            $table->foreign('billable_service_id')->references('id')->on('billable_services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_prices', function (Blueprint $table): void {
            $table->dropForeign(['billable_service_id']);
            $table->foreign('billable_service_id')->references('id')->on('billable_services')->restrictOnDelete();
        });
    }
};
