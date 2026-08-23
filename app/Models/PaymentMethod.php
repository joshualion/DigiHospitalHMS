<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = ['hospital_id', 'code', 'name', 'type', 'reference_fields', 'requires_open_shift', 'is_active'];

    protected function casts(): array
    {
        return ['reference_fields' => 'array', 'requires_open_shift' => 'boolean', 'is_active' => 'boolean'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
