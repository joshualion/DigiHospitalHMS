<?php

namespace App\Models;

use App\Services\SensitiveLookup;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class NotificationDelivery extends Model
{
    protected $fillable=['hospital_id','notification_template_id','appointment_id','patient_id','channel','recipient_encrypted','recipient_hash','subject','body','status','provider','provider_reference','error_message','scheduled_for','attempted_at','sent_at','fingerprint'];
    protected $appends=['recipient'];
    protected function casts(): array { return ['scheduled_for'=>'datetime','attempted_at'=>'datetime','sent_at'=>'datetime']; }
    public function template(): BelongsTo { return $this->belongsTo(NotificationTemplate::class,'notification_template_id'); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function setRecipientAttribute(?string $value): void
    {
        $this->attributes['recipient_encrypted']=filled($value)?Crypt::encryptString($value):null;
        $this->attributes['recipient_hash']=app(SensitiveLookup::class)->hash($value);
    }
    protected function recipient(): Attribute
    {
        return Attribute::get(fn(): ?string => $this->recipient_encrypted ? Crypt::decryptString($this->recipient_encrypted) : null);
    }
}
