<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    protected $fillable=['hospital_id','key','channel','name','subject','body','reminder_minutes_before','is_active'];
    protected function casts(): array { return ['is_active'=>'boolean','reminder_minutes_before'=>'integer']; }
    public function deliveries(): HasMany { return $this->hasMany(NotificationDelivery::class); }
}
