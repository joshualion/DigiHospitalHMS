<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientBloodGroup extends Model
{
    protected $fillable = ['hospital_id', 'patient_id', 'blood_request_specimen_id', 'abo_group', 'rh_factor', 'status', 'notes', 'entered_by', 'entered_at', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['entered_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(BloodRequestSpecimen::class, 'blood_request_specimen_id');
    }
}
