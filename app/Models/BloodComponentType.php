<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodComponentType extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'default_shelf_life_days', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
