<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublicSiteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'public_site_section_id',
        'draft_public_site_section_id',
        'published_public_site_section_id',
        'presentable_type',
        'presentable_id',
        'type',
        'draft_type',
        'published_type',
        'slug',
        'draft_slug',
        'published_slug',
        'title',
        'draft_title',
        'published_title',
        'summary',
        'draft_summary',
        'published_summary',
        'draft_content',
        'published_content',
        'status',
        'is_enabled',
        'draft_is_enabled',
        'published_is_enabled',
        'is_featured',
        'draft_is_featured',
        'published_is_featured',
        'sort_order',
        'draft_sort_order',
        'published_sort_order',
        'published_version',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'draft_content' => 'array',
            'published_content' => 'array',
            'is_enabled' => 'boolean',
            'draft_is_enabled' => 'boolean',
            'published_is_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'draft_is_featured' => 'boolean',
            'published_is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PublicSiteSection::class, 'public_site_section_id');
    }

    public function presentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_is_enabled', true)->whereNotNull('published_at');
    }

    public function liveContent(): array
    {
        return $this->published_content ?? [];
    }

    public function draftSnapshot(): array
    {
        return [
            'public_site_section_id' => $this->draft_public_site_section_id ?? $this->public_site_section_id,
            'type' => $this->draft_type ?? $this->type,
            'slug' => $this->draft_slug ?? $this->slug,
            'title' => $this->draft_title ?? $this->title,
            'summary' => $this->draft_summary ?? $this->summary,
            'content' => $this->draft_content ?? [],
            'is_enabled' => $this->draft_is_enabled ?? $this->is_enabled,
            'is_featured' => $this->draft_is_featured ?? $this->is_featured,
            'sort_order' => $this->draft_sort_order ?? $this->sort_order,
        ];
    }

    public function publishedSnapshot(): array
    {
        return [
            'public_site_section_id' => $this->published_public_site_section_id,
            'type' => $this->published_type ?? $this->type,
            'slug' => $this->published_slug ?? $this->slug,
            'title' => $this->published_title ?? $this->title,
            'summary' => $this->published_summary ?? $this->summary,
            'content' => $this->published_content ?? [],
            'is_enabled' => $this->published_is_enabled ?? $this->is_enabled,
            'is_featured' => $this->published_is_featured ?? $this->is_featured,
            'sort_order' => $this->published_sort_order ?? $this->sort_order,
        ];
    }
}
