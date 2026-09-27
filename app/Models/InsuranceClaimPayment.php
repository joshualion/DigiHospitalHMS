<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimPayment extends Model
{
    protected $fillable=['hospital_id','insurance_claim_id','amount_minor','reference','received_on','recorded_by','notes'];
    protected function casts(): array { return ['received_on'=>'date']; }
    public function claim(): BelongsTo { return $this->belongsTo(InsuranceClaim::class,'insurance_claim_id'); }
}
