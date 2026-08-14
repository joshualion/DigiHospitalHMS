<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'name',
        'code',
        'facility_type',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'timezone',
        'is_primary',
        'status',
        'opening_hours',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'opening_hours' => 'array',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(FacilityMembership::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
