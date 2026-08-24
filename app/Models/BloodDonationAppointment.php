<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodDonationAppointment extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'blood_donor_id', 'blood_bank_location_id', 'scheduled_at', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }
}
