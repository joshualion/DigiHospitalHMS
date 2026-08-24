<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionAmendment extends Model
{
    protected $fillable = ['hospital_id', 'prescription_id', 'reason', 'content', 'authored_by', 'authored_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }
}
