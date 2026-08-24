<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodDonorCategory extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
