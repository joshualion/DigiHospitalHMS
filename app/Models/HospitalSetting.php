<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospitalSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'default_facility_id',
        'locale',
        'timezone',
        'currency',
        'date_format',
        'time_format',
        'branding',
        'contact_details',
        'operating_preferences',
        'public_site_defaults',
        'numbering_preferences',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'contact_details' => 'array',
            'operating_preferences' => 'array',
            'public_site_defaults' => 'array',
            'numbering_preferences' => 'array',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function defaultFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'default_facility_id');
    }
}
