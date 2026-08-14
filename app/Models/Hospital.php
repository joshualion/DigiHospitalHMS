<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hospital extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_name',
        'display_name',
        'registration_reference',
        'email',
        'phone_numbers',
        'website',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'timezone',
        'logo_path',
        'status',
        'primary_contact_name',
        'primary_contact_email',
        'primary_contact_phone',
        'default_currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'phone_numbers' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(HospitalSetting::class);
    }

    public function numberSequences(): HasMany
    {
        return $this->hasMany(NumberSequence::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public static function primary(): ?self
    {
        return self::query()->oldest('id')->first();
    }
}
