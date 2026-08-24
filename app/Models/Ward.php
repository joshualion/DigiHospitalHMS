<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'department_id', 'code', 'name', 'status', 'notes'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(WardRoom::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
