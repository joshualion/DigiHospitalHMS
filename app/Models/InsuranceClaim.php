<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceClaim extends Model
{
    protected $fillable=['hospital_id','invoice_id','patient_id','patient_coverage_id','payer_organization_id','payer_plan_id','claim_number','submission_round','status','currency','claimed_minor','approved_minor','paid_minor','outstanding_minor','external_reference','submission_notes','decision_notes','submitted_at','decided_at','last_resubmitted_at','created_by','submitted_by','decided_by'];
    protected function casts(): array { return ['submitted_at'=>'datetime','decided_at'=>'datetime','last_resubmitted_at'=>'datetime']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function coverage(): BelongsTo { return $this->belongsTo(PatientCoverage::class,'patient_coverage_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(PayerOrganization::class,'payer_organization_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(PayerPlan::class,'payer_plan_id'); }
    public function lines(): HasMany { return $this->hasMany(InsuranceClaimLine::class); }
    public function events(): HasMany { return $this->hasMany(InsuranceClaimEvent::class); }
    public function payments(): HasMany { return $this->hasMany(InsuranceClaimPayment::class); }
}
