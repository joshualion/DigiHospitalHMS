<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementApprovalLimit extends Model
{
    protected $fillable = ['hospital_id', 'role_name', 'limit_minor', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['limit_minor' => 'integer', 'is_active' => 'boolean'];
    }
}
