<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloodBankEvent extends Model
{
    protected $fillable = ['hospital_id', 'subject_type', 'subject_id', 'actor_id', 'action', 'from_status', 'to_status', 'before', 'after', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => 'datetime'];
    }
}
