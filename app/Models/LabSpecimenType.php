<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabSpecimenType extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'collection_notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
