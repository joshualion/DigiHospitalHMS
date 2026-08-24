<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WardRoom extends Model
{
    protected $fillable = ['hospital_id', 'ward_id', 'code', 'name', 'status'];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
