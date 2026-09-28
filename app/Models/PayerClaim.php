<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerClaim extends Model
{
    protected $fillable = [
        'hospital_id','payer_claim_batch_id','patient_id','patient_coverage_id','payer_organization_id',
        'payer_plan_id','invoice_id','payer_pre_authorization_id','resubmission_of_claim_id','claim_number',
        'payer_reference','status','currency','claimed_minor','approved_minor','paid_minor','service_date',
        'due_date','submission_notes','decision_notes','rejection_reason','created_by','submitted_by',
        'submitted_at','decided_by','decided_at',
    ];

    protected function casts(): array
    {
        return ['service_date'=>'date','due_date'=>'date','submitted_at'=>'datetime','decided_at'=>'datetime'];
    }

    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function coverage(): BelongsTo { return $this->belongsTo(PatientCoverage::class,'patient_coverage_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(PayerOrganization::class,'payer_organization_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(PayerPlan::class,'payer_plan_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function batch(): BelongsTo { return $this->belongsTo(PayerClaimBatch::class,'payer_claim_batch_id'); }
    public function preAuthorization(): BelongsTo { return $this->belongsTo(PayerPreAuthorization::class,'payer_pre_authorization_id'); }

    public function outstandingMinor(): int
    {
        $basis = $this->approved_minor ?? $this->claimed_minor;
        return max(0, $basis - $this->paid_minor);
    }
}
