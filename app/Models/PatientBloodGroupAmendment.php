<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientBloodGroupAmendment extends Model
{
    protected $fillable = ['hospital_id', 'patient_id', 'patient_blood_group_id', 'abo_group', 'rh_factor', 'reason', 'authored_by', 'authored_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
