<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayerClaimBatch extends Model
{
    protected $fillable = [
        'hospital_id','payer_organization_id','reference','currency','status','claimed_minor',
        'approved_minor','paid_minor','due_date','created_by','submitted_by','submitted_at','notes',
    ];

    protected function casts(): array
    {
        return ['due_date'=>'date','submitted_at'=>'datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(PayerOrganization::class,'payer_organization_id'); }
    public function claims(): HasMany { return $this->hasMany(PayerClaim::class); }
}
