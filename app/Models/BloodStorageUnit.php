<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodStorageUnit extends Model
{
    protected $fillable = ['hospital_id', 'blood_bank_location_id', 'code', 'name', 'storage_type', 'status', 'notes'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(BloodBankLocation::class, 'blood_bank_location_id');
    }
}
