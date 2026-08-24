<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodScreeningTest extends Model
{
    protected $fillable = ['hospital_id', 'lab_test_id', 'code', 'name', 'is_required_for_release', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_required_for_release' => 'boolean', 'is_active' => 'boolean'];
    }
}
