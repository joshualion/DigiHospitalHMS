<?php

namespace App\Models;

use App\Services\SensitiveLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class BloodDonor extends Model
{
    protected $fillable = [
        'hospital_id', 'blood_donor_category_id', 'registered_by', 'donor_number', 'status',
        'first_name', 'middle_name', 'last_name', 'date_of_birth', 'sex',
        'address_encrypted', 'phone_encrypted', 'phone_hash', 'email_encrypted', 'email_hash',
        'identifier_type', 'identifier_encrypted', 'identifier_hash', 'consented_at',
        'consent_reference', 'notes',
    ];

    protected $appends = ['full_name', 'address', 'phone', 'email', 'identifier_value'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'consented_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BloodDonorCategory::class, 'blood_donor_category_id');
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(BloodDonorScreening::class);
    }

    public function deferrals(): HasMany
    {
        return $this->hasMany(BloodDonorDeferral::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(BloodDonation::class);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! filled($search)) {
            return $query;
        }

        $hash = app(SensitiveLookup::class)->hash($search);

        return $query->where(function ($inner) use ($search, $hash): void {
            $inner->where('donor_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone_hash', $hash)
                ->orWhere('email_hash', $hash)
                ->orWhere('identifier_hash', $hash);
        });
    }

    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['address_encrypted'] = filled($value) ? Crypt::encryptString($value) : null;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone_encrypted'] = filled($value) ? Crypt::encryptString($value) : null;
        $this->attributes['phone_hash'] = app(SensitiveLookup::class)->hash($value);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email_encrypted'] = filled($value) ? Crypt::encryptString($value) : null;
        $this->attributes['email_hash'] = app(SensitiveLookup::class)->hash($value);
    }

    public function setIdentifierValueAttribute(?string $value): void
    {
        $this->attributes['identifier_encrypted'] = filled($value) ? Crypt::encryptString($value) : null;
        $this->attributes['identifier_hash'] = app(SensitiveLookup::class)->hash($value);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name]))));
    }

    protected function address(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->address_encrypted ? Crypt::decryptString($this->address_encrypted) : null);
    }

    protected function phone(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->phone_encrypted ? Crypt::decryptString($this->phone_encrypted) : null);
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->email_encrypted ? Crypt::decryptString($this->email_encrypted) : null);
    }

    protected function identifierValue(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->identifier_encrypted ? Crypt::decryptString($this->identifier_encrypted) : null);
    }
}
