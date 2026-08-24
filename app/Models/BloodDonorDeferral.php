<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodDonorDeferral extends Model
{
    protected $fillable = ['hospital_id', 'blood_donor_id', 'recorded_by', 'deferral_type', 'reason', 'deferred_until', 'recorded_at'];

    protected function casts(): array
    {
        return ['deferred_until' => 'date', 'recorded_at' => 'datetime'];
    }
}
