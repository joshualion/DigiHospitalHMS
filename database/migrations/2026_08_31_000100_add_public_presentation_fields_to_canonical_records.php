<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->boolean('public_is_visible')->default(false)->after('is_active');
            $table->boolean('public_is_featured')->default(false)->after('public_is_visible');
            $table->string('public_slug')->nullable()->after('public_is_featured');
            $table->string('public_display_name')->nullable()->after('public_slug');
            $table->string('public_specialty')->nullable()->after('public_display_name');
            $table->text('public_summary')->nullable()->after('public_specialty');
            $table->string('public_photo_path')->nullable()->after('public_summary');
            $table->string('public_photo_alt')->nullable()->after('public_photo_path');
            $table->unsignedInteger('public_display_order')->default(0)->after('public_photo_alt');

            $table->index(['hospital_id', 'public_is_visible', 'public_is_featured'], 'staff_public_visibility_index');
            $table->unique(['hospital_id', 'public_slug'], 'staff_public_slug_unique');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->boolean('public_is_visible')->default(false)->after('display_order');
            $table->boolean('public_is_featured')->default(false)->after('public_is_visible');
            $table->string('public_slug')->nullable()->after('public_is_featured');
            $table->string('public_name')->nullable()->after('public_slug');
            $table->text('public_description')->nullable()->after('public_name');
            $table->string('public_icon')->nullable()->after('public_description');
            $table->string('public_image_path')->nullable()->after('public_icon');
            $table->unsignedInteger('public_display_order')->default(0)->after('public_image_path');

            $table->index(['hospital_id', 'public_is_visible', 'public_is_featured'], 'departments_public_visibility_index');
            $table->unique(['hospital_id', 'public_slug'], 'departments_public_slug_unique');
        });

        Schema::table('billable_services', function (Blueprint $table): void {
            $table->boolean('public_is_visible')->default(false)->after('is_active');
            $table->boolean('public_is_featured')->default(false)->after('public_is_visible');
            $table->string('public_slug')->nullable()->after('public_is_featured');
            $table->string('public_name')->nullable()->after('public_slug');
            $table->text('public_description')->nullable()->after('public_name');
            $table->string('public_icon')->nullable()->after('public_description');
            $table->string('public_image_path')->nullable()->after('public_icon');
            $table->unsignedInteger('public_display_order')->default(0)->after('public_image_path');

            $table->index(['hospital_id', 'public_is_visible', 'public_is_featured'], 'services_public_visibility_index');
            $table->unique(['hospital_id', 'public_slug'], 'services_public_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('billable_services', function (Blueprint $table): void {
            $table->dropUnique('services_public_slug_unique');
            $table->dropIndex('services_public_visibility_index');
            $table->dropColumn([
                'public_is_visible',
                'public_is_featured',
                'public_slug',
                'public_name',
                'public_description',
                'public_icon',
                'public_image_path',
                'public_display_order',
            ]);
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique('departments_public_slug_unique');
            $table->dropIndex('departments_public_visibility_index');
            $table->dropColumn([
                'public_is_visible',
                'public_is_featured',
                'public_slug',
                'public_name',
                'public_description',
                'public_icon',
                'public_image_path',
                'public_display_order',
            ]);
        });

        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropUnique('staff_public_slug_unique');
            $table->dropIndex('staff_public_visibility_index');
            $table->dropColumn([
                'public_is_visible',
                'public_is_featured',
                'public_slug',
                'public_display_name',
                'public_specialty',
                'public_summary',
                'public_photo_path',
                'public_photo_alt',
                'public_display_order',
            ]);
        });
    }
};
