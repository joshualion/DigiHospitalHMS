<?php

namespace App\Models;

use App\Services\SensitiveLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PatientIdentifier extends Model
{
    protected $fillable = ['patient_id', 'hospital_id', 'type', 'value_encrypted', 'value_hash', 'is_searchable', 'recorded_by'];

    protected $appends = ['value'];

    protected function casts(): array
    {
        return [
            'is_searchable' => 'boolean',
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

    public function setValueAttribute(?string $value): void
    {
        $this->attributes['value_encrypted'] = filled($value) ? Crypt::encryptString($value) : null;
        $this->attributes['value_hash'] = app(SensitiveLookup::class)->hash($value);
    }

    protected function value(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->value_encrypted ? Crypt::decryptString($this->value_encrypted) : null);
    }
}
