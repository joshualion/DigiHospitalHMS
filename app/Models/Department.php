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
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
