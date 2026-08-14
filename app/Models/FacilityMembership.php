<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_profile_id',
        'facility_id',
        'department_id',
        'facility_title',
        'is_default',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
