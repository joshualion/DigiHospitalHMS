<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BedClass extends Model
{
    protected $fillable = ['hospital_id', 'billable_service_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function billableService(): BelongsTo
    {
        return $this->belongsTo(BillableService::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }
}
