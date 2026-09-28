<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupMonitorEvent extends Model
{
    protected $fillable=['hospital_id','status','source','backup_reference','size_bytes','backup_completed_at','restore_verified_at','notes','recorded_by'];
    protected function casts(): array { return ['backup_completed_at'=>'datetime','restore_verified_at'=>'datetime','size_bytes'=>'integer']; }
}
