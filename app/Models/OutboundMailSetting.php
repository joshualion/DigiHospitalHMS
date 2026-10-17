<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboundMailSetting extends Model
{
    protected $fillable = ['enabled', 'provider', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'password' => 'encrypted', 'port' => 'integer'];
    }
}
