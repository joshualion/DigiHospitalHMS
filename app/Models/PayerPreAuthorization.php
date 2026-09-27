<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerPreAuthorization extends Model
{
    protected $fillable = ['hospital_id','patient_coverage_id','patient_id','billable_service_id','clinical_encounter_id','reference','authorization_code','status','requested_amount_minor','approved_amount_minor','clinical_or_service_context','decision_notes','requested_by','requested_at','decided_by','decided_at','valid_until'];
    protected function casts(): array { return ['requested_at'=>'datetime','decided_at'=>'datetime','valid_until'=>'date']; }
    public function coverage(): BelongsTo { return $this->belongsTo(PatientCoverage::class,'patient_coverage_id'); }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function service(): BelongsTo { return $this->belongsTo(BillableService::class,'billable_service_id'); }
}
