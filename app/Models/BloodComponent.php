<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BloodComponent extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'blood_donation_id', 'blood_component_type_id', 'blood_bank_location_id', 'blood_storage_unit_id', 'component_number', 'abo_group', 'rh_factor', 'volume_ml', 'expires_on', 'state', 'prepared_by', 'prepared_at', 'released_by', 'released_at', 'release_reason', 'notes'];

    protected function casts(): array
    {
        return ['expires_on' => 'date', 'prepared_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(BloodDonation::class, 'blood_donation_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(BloodComponentType::class, 'blood_component_type_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(BloodBankLocation::class, 'blood_bank_location_id');
    }

    public function storageUnit(): BelongsTo
    {
        return $this->belongsTo(BloodStorageUnit::class, 'blood_storage_unit_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(BloodComponentTransfer::class);
    }

    public function isUsableCandidate(): bool
    {
        return $this->state === 'available' && (! $this->expires_on || $this->expires_on->isFuture() || $this->expires_on->isToday());
    }
}
