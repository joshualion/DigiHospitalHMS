<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabUnit extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
