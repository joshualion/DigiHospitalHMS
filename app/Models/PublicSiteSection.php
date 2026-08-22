<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicSiteSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_site_page_id',
        'key',
        'type',
        'label',
        'draft_label',
        'published_label',
        'sort_order',
        'draft_sort_order',
        'published_sort_order',
        'is_enabled',
        'draft_is_enabled',
        'published_is_enabled',
        'draft_content',
        'published_content',
        'published_version',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'draft_is_enabled' => 'boolean',
            'published_is_enabled' => 'boolean',
            'draft_content' => 'array',
            'published_content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PublicSitePage::class, 'public_site_page_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PublicSiteItem::class)->orderBy('sort_order');
    }

    public function liveContent(): array
    {
        return $this->published_content ?? [];
    }

    public function draftSnapshot(): array
    {
        return [
            'label' => $this->draft_label ?? $this->label,
            'sort_order' => $this->draft_sort_order ?? $this->sort_order,
            'is_enabled' => $this->draft_is_enabled ?? $this->is_enabled,
            'content' => $this->draft_content ?? [],
        ];
    }

    public function publishedSnapshot(): array
    {
        return [
            'label' => $this->published_label ?? $this->label,
            'sort_order' => $this->published_sort_order ?? $this->sort_order,
            'is_enabled' => $this->published_is_enabled ?? $this->is_enabled,
            'content' => $this->published_content ?? [],
        ];
    }
}
