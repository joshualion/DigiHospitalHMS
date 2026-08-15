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
        'sort_order',
        'is_enabled',
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
}
