<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_site_pages', function (Blueprint $table): void {
            $table->string('draft_title')->nullable()->after('title');
            $table->string('published_title')->nullable()->after('draft_title');
            $table->json('draft_seo')->nullable()->after('seo');
            $table->json('published_seo')->nullable()->after('draft_seo');
        });

        Schema::table('public_site_sections', function (Blueprint $table): void {
            $table->string('draft_label')->nullable()->after('label');
            $table->string('published_label')->nullable()->after('draft_label');
            $table->unsignedInteger('draft_sort_order')->nullable()->after('sort_order');
            $table->unsignedInteger('published_sort_order')->nullable()->after('draft_sort_order');
            $table->boolean('draft_is_enabled')->nullable()->after('is_enabled');
            $table->boolean('published_is_enabled')->nullable()->after('draft_is_enabled');
        });

        Schema::table('public_site_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('draft_public_site_section_id')->nullable()->after('public_site_section_id');
            $table->unsignedBigInteger('published_public_site_section_id')->nullable()->after('draft_public_site_section_id');
            $table->string('draft_type')->nullable()->after('type');
            $table->string('published_type')->nullable()->after('draft_type');
            $table->string('draft_slug')->nullable()->after('slug');
            $table->string('published_slug')->nullable()->after('draft_slug');
            $table->string('draft_title')->nullable()->after('title');
            $table->string('published_title')->nullable()->after('draft_title');
            $table->text('draft_summary')->nullable()->after('summary');
            $table->text('published_summary')->nullable()->after('draft_summary');
            $table->boolean('draft_is_enabled')->nullable()->after('is_enabled');
            $table->boolean('published_is_enabled')->nullable()->after('draft_is_enabled');
            $table->boolean('draft_is_featured')->nullable()->after('is_featured');
            $table->boolean('published_is_featured')->nullable()->after('draft_is_featured');
            $table->unsignedInteger('draft_sort_order')->nullable()->after('sort_order');
            $table->unsignedInteger('published_sort_order')->nullable()->after('draft_sort_order');
        });

        DB::table('public_site_pages')->orderBy('id')->each(function (object $page): void {
            DB::table('public_site_pages')->where('id', $page->id)->update([
                'draft_title' => $page->title,
                'published_title' => $page->status === 'published' ? $page->title : null,
                'draft_seo' => $page->seo,
                'published_seo' => $page->status === 'published' ? $page->seo : null,
            ]);
        });

        DB::table('public_site_sections')->orderBy('id')->each(function (object $section): void {
            DB::table('public_site_sections')->where('id', $section->id)->update([
                'draft_label' => $section->label,
                'published_label' => $section->published_at ? $section->label : null,
                'draft_sort_order' => $section->sort_order,
                'published_sort_order' => $section->published_at ? $section->sort_order : null,
                'draft_is_enabled' => $section->is_enabled,
                'published_is_enabled' => $section->published_at ? $section->is_enabled : null,
            ]);
        });

        DB::table('public_site_items')->orderBy('id')->each(function (object $item): void {
            DB::table('public_site_items')->where('id', $item->id)->update([
                'draft_public_site_section_id' => $item->public_site_section_id,
                'published_public_site_section_id' => $item->status === 'published' ? $item->public_site_section_id : null,
                'draft_type' => $item->type,
                'published_type' => $item->status === 'published' ? $item->type : null,
                'draft_slug' => $item->slug,
                'published_slug' => $item->status === 'published' ? $item->slug : null,
                'draft_title' => $item->title,
                'published_title' => $item->status === 'published' ? $item->title : null,
                'draft_summary' => $item->summary,
                'published_summary' => $item->status === 'published' ? $item->summary : null,
                'draft_is_enabled' => $item->is_enabled,
                'published_is_enabled' => $item->status === 'published' ? $item->is_enabled : null,
                'draft_is_featured' => $item->is_featured,
                'published_is_featured' => $item->status === 'published' ? $item->is_featured : null,
                'draft_sort_order' => $item->sort_order,
                'published_sort_order' => $item->status === 'published' ? $item->sort_order : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('public_site_items', function (Blueprint $table): void {
            $table->dropColumn([
                'draft_public_site_section_id',
                'published_public_site_section_id',
                'draft_type',
                'published_type',
                'draft_slug',
                'published_slug',
                'draft_title',
                'published_title',
                'draft_summary',
                'published_summary',
                'draft_is_enabled',
                'published_is_enabled',
                'draft_is_featured',
                'published_is_featured',
                'draft_sort_order',
                'published_sort_order',
            ]);
        });

        Schema::table('public_site_sections', function (Blueprint $table): void {
            $table->dropColumn([
                'draft_label',
                'published_label',
                'draft_sort_order',
                'published_sort_order',
                'draft_is_enabled',
                'published_is_enabled',
            ]);
        });

        Schema::table('public_site_pages', function (Blueprint $table): void {
            $table->dropColumn(['draft_title', 'published_title', 'draft_seo', 'published_seo']);
        });
    }
};
