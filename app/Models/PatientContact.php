<?php

namespace App\Models;

use App\Services\SensitiveLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PatientContact extends Model
{
    protected $fillable = ['patient_id', 'hospital_id', 'type', 'name', 'relationship', 'phone_encrypted', 'phone_hash', 'email_encrypted', 'email_hash', 'address', 'is_next_of_kin', 'is_primary', 'recorded_by'];

    protected $appends = ['phone', 'email'];

    protected function casts(): array
    {
        return [
            'is_next_of_kin' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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

    protected function phone(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->phone_encrypted ? Crypt::decryptString($this->phone_encrypted) : null);
    }

    protected function email(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->email_encrypted ? Crypt::decryptString($this->email_encrypted) : null);
    }
}
