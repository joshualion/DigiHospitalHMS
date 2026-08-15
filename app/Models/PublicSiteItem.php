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
        'presentable_type',
        'presentable_id',
        'type',
        'slug',
        'title',
        'summary',
        'draft_content',
        'published_content',
        'status',
        'is_enabled',
        'is_featured',
        'sort_order',
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
            'is_featured' => 'boolean',
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
        return $query->where('status', 'published')->where('is_enabled', true)->whereNotNull('published_at');
    }

    public function liveContent(): array
    {
        return $this->published_content ?? [];
    }
}
