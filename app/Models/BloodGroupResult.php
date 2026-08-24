<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodGroupResult extends Model
{
    protected $fillable = ['hospital_id', 'blood_donation_id', 'abo_group', 'rh_factor', 'status', 'notes', 'entered_by', 'entered_at', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['entered_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(BloodDonation::class, 'blood_donation_id');
    }
}
