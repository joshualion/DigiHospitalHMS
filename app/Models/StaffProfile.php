<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hospital_id',
        'staff_number',
        'job_title',
        'staff_category',
        'professional_license_number',
        'license_expires_at',
        'work_phone',
        'employment_status',
        'hire_date',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'license_expires_at' => 'date',
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(FacilityMembership::class);
    }

    public function defaultMembership(): ?FacilityMembership
    {
        return $this->memberships()
            ->where('is_default', true)
            ->where('status', 'active')
            ->first();
    }
}
