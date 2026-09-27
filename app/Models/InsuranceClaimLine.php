<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaimLine extends Model
{
    protected $fillable=['insurance_claim_id','invoice_line_id','billable_service_id','service_code','service_name','quantity','claimed_minor','approved_minor','decision_reason'];
    public function claim(): BelongsTo { return $this->belongsTo(InsuranceClaim::class,'insurance_claim_id'); }
}
