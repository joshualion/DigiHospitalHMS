<?php

namespace App\Models;

use App\Services\SensitiveLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Patient extends Model
{
    protected $fillable = [
        'hospital_id',
        'registration_facility_id',
        'registered_by',
        'hospital_number',
        'status',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'estimated_age_years',
        'is_dob_estimated',
        'sex',
        'marital_status',
        'occupation',
        'address',
        'phone_encrypted',
        'phone_hash',
        'email_encrypted',
        'email_hash',
        'archived_at',
        'archived_by',
        'deceased_at',
        'deceased_by',
        'status_reason',
    ];

    protected $appends = ['full_name', 'phone', 'email'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_dob_estimated' => 'boolean',
            'archived_at' => 'datetime',
            'deceased_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function registrationFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'registration_facility_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(PatientIdentifier::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PatientContact::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PatientAlert::class);
    }

    public function activityEvents(): HasMany
    {
        return $this->hasMany(PatientActivityEvent::class)->latest('occurred_at');
    }

    public function labRequests(): HasMany
    {
        return $this->hasMany(LabRequest::class);
    }

    public function radiologyRequests(): HasMany
    {
        return $this->hasMany(RadiologyRequest::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function scopeForHospital($query, int $hospitalId)
    {
        return $query->where('hospital_id', $hospitalId);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! filled($search)) {
            return $query;
        }

        $lookupHash = app(SensitiveLookup::class)->hash($search);

        return $query->where(function ($inner) use ($search, $lookupHash): void {
            $inner->where('hospital_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone_hash', $lookupHash)
                ->orWhereHas('identifiers', fn ($identifier) => $identifier
                    ->where('is_searchable', true)
                    ->where('value_hash', $lookupHash));
        });
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

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]))));
    }

    protected function phone(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->phone_encrypted ? Crypt::decryptString($this->phone_encrypted) : null);
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->email_encrypted ? Crypt::decryptString($this->email_encrypted) : null);
    }
}
