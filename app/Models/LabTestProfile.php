<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LabTestProfile extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(LabTest::class, 'lab_test_profile_test')->withPivot('sort_order')->withTimestamps();
    }
}
