<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayerPlan extends Model
{
    protected $fillable = ['hospital_id','payer_organization_id','code','name','currency','requires_pre_authorization','status','effective_from','effective_to','notes'];
    protected function casts(): array { return ['requires_pre_authorization'=>'boolean','effective_from'=>'date','effective_to'=>'date']; }
    public function organization(): BelongsTo { return $this->belongsTo(PayerOrganization::class,'payer_organization_id'); }
    public function tariffs(): HasMany { return $this->hasMany(PayerPlanTariff::class); }
    public function coverages(): HasMany { return $this->hasMany(PatientCoverage::class); }
}
