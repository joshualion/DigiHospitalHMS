<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicSitePage extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'slug',
        'title',
        'template',
        'status',
        'draft_content',
        'published_content',
        'seo',
        'published_version',
        'published_at',
        'published_by',
        'unpublished_at',
    ];

    protected function casts(): array
    {
        return [
            'draft_content' => 'array',
            'published_content' => 'array',
            'seo' => 'array',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PublicSiteSection::class)->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PublicSiteRevision::class, 'revisionable_id')
            ->where('revisionable_type', self::class)
            ->latest('version');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function liveContent(): array
    {
        return $this->published_content ?? [];
    }
}
