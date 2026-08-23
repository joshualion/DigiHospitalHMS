<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RadiologyModality extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function studies(): HasMany
    {
        return $this->hasMany(RadiologyStudy::class);
    }
}
