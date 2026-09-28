<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConfiguration extends Model
{
    protected $fillable=['hospital_id','type','provider','status','configuration','last_checked_at','last_check_status','last_check_message'];
    protected function casts(): array { return ['configuration'=>'array','last_checked_at'=>'datetime']; }
}
