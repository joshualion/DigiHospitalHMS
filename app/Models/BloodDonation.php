<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BloodDonation extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'blood_donor_id', 'blood_donation_appointment_id', 'blood_bank_location_id', 'donation_number', 'collection_number', 'collected_at', 'collected_by', 'bag_type', 'volume_ml', 'status', 'notes'];

    protected function casts(): array
    {
        return ['collected_at' => 'datetime'];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(BloodDonor::class, 'blood_donor_id');
    }

    public function groupResult(): HasOne
    {
        return $this->hasOne(BloodGroupResult::class);
    }

    public function screeningResults(): HasMany
    {
        return $this->hasMany(BloodScreeningResult::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(BloodComponent::class);
    }
}
