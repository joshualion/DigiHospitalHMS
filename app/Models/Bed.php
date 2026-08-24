<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bed extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'ward_id', 'ward_room_id', 'bed_class_id', 'code', 'label', 'state', 'state_reason'];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(WardRoom::class, 'ward_room_id');
    }

    public function bedClass(): BelongsTo
    {
        return $this->belongsTo(BedClass::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AdmissionBedMovement::class, 'to_bed_id');
    }
}
