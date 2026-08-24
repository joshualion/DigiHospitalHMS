<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionBedMovement extends Model
{
    protected $fillable = ['hospital_id', 'admission_id', 'from_facility_id', 'to_facility_id', 'from_department_id', 'to_department_id', 'from_ward_id', 'to_ward_id', 'from_bed_id', 'to_bed_id', 'movement_type', 'started_at', 'ended_at', 'reason', 'performed_by'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function toBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'to_bed_id');
    }
}
