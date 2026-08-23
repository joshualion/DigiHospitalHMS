<?php

namespace App\Models;

use App\Services\SensitiveLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PublicAppointmentRequest extends Model
{
    protected $fillable = ['hospital_id', 'preferred_facility_id', 'preferred_department_id', 'name', 'phone_encrypted', 'phone_hash', 'email_encrypted', 'email_hash', 'preferred_date', 'consent', 'status', 'patient_id', 'appointment_id', 'reviewed_by', 'reviewed_at', 'review_reason', 'ip_hash'];

    protected $appends = ['phone', 'email'];

    protected function casts(): array
    {
        return ['preferred_date' => 'date', 'consent' => 'boolean', 'reviewed_at' => 'datetime'];
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
