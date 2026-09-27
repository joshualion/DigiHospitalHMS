<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientCoverage extends Model
{
    protected $fillable = ['hospital_id','patient_id','payer_organization_id','payer_plan_id','member_number','policy_number','principal_member_name','relationship_to_principal','employer_name','valid_from','valid_to','is_primary','status','notes'];
    protected function casts(): array { return ['valid_from'=>'date','valid_to'=>'date','is_primary'=>'boolean']; }
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function organization(): BelongsTo { return $this->belongsTo(PayerOrganization::class,'payer_organization_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(PayerPlan::class,'payer_plan_id'); }
    public function preAuthorizations(): HasMany { return $this->hasMany(PayerPreAuthorization::class); }
    public function isCurrentlyValid(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->valid_from && $this->valid_from->isFuture()) return false;
        if ($this->valid_to && $this->valid_to->isPast()) return false;
        return true;
    }
}
