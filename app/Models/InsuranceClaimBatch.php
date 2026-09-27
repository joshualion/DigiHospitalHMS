<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InsuranceClaimBatch extends Model
{
    protected $fillable=['hospital_id','payer_organization_id','batch_number','status','currency','claim_count','total_claimed_minor','external_reference','submitted_at','acknowledged_at','closed_at','created_by','submitted_by','notes'];
    protected function casts(): array { return ['submitted_at'=>'datetime','acknowledged_at'=>'datetime','closed_at'=>'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(PayerOrganization::class,'payer_organization_id'); }
    public function claims(): BelongsToMany { return $this->belongsToMany(InsuranceClaim::class,'insurance_claim_batch_items')->withTimestamps(); }
}
