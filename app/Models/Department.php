<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'facility_id',
        'name',
        'code',
        'description',
        'category',
        'status',
        'display_order',
        'public_is_visible',
        'public_is_featured',
        'public_slug',
        'public_name',
        'public_description',
        'public_icon',
        'public_image_path',
        'public_display_order',
    ];

    protected function casts(): array
    {
        return [
            'public_is_visible' => 'boolean',
            'public_is_featured' => 'boolean',
            'public_display_order' => 'integer',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
