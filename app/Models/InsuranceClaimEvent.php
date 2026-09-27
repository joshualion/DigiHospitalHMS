<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimEvent extends Model
{
    protected $fillable=['insurance_claim_id','hospital_id','actor_id','action','from_status','to_status','payload','reason','occurred_at'];
    protected function casts(): array { return ['payload'=>'array','occurred_at'=>'datetime']; }
    public function claim(): BelongsTo { return $this->belongsTo(InsuranceClaim::class,'insurance_claim_id'); }
}
