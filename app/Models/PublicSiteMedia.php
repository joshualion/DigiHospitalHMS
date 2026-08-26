<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PublicSiteMedia extends Model
{
    use HasFactory;

    protected $appends = [
        'url',
    ];

    protected $fillable = [
        'hospital_id',
        'title',
        'alt_text',
        'caption',
        'credit',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'focal_point',
        'usage_count',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'focal_point' => 'array',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'public') {
            return Storage::url($this->path);
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
