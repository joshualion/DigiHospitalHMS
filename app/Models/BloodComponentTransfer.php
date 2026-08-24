<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodComponentTransfer extends Model
{
    protected $fillable = ['hospital_id', 'blood_component_id', 'from_location_id', 'to_location_id', 'from_storage_unit_id', 'to_storage_unit_id', 'status', 'reason', 'transferred_by', 'transferred_at'];

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime'];
    }
}
