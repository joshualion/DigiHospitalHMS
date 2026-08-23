<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentEvent extends Model
{
    protected $fillable = ['appointment_id', 'hospital_id', 'actor_id', 'action', 'from_status', 'to_status', 'before', 'after', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => 'datetime'];
    }
}
