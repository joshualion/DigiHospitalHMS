<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTest extends Model
{
    protected $fillable = ['hospital_id', 'department_id', 'default_specimen_type_id', 'billable_service_id', 'code', 'name', 'description', 'turnaround_time', 'requires_approval', 'is_active'];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean', 'is_active' => 'boolean'];
    }

    public function components(): HasMany
    {
        return $this->hasMany(LabTestComponent::class)->orderBy('sort_order');
    }

    public function specimenType(): BelongsTo
    {
        return $this->belongsTo(LabSpecimenType::class, 'default_specimen_type_id');
    }

    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class);
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(LabTestProfile::class, 'lab_test_profile_test')->withPivot('sort_order')->withTimestamps();
    }
}
