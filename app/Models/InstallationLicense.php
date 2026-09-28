<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationLicense extends Model
{
    protected $fillable=['hospital_id','license_key','plan','status','starts_on','expires_on','licensed_facilities','notes'];
    protected function casts(): array { return ['starts_on'=>'date','expires_on'=>'date','licensed_facilities'=>'integer']; }
}
