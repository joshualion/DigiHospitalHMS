<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicianSchedule extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'department_id', 'staff_profile_id', 'day_of_week', 'starts_at', 'ends_at', 'breaks', 'is_active'];

    protected function casts(): array
    {
        return ['breaks' => 'array', 'is_active' => 'boolean'];
    }

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }
}
