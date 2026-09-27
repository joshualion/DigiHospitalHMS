<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayerPlanTariff extends Model
{
    protected $fillable = ['hospital_id','payer_plan_id','billable_service_id','facility_id','currency','amount_minor','effective_from','effective_to','is_active','notes','created_by'];
    protected function casts(): array { return ['effective_from'=>'date','effective_to'=>'date','is_active'=>'boolean']; }
    public function plan(): BelongsTo { return $this->belongsTo(PayerPlan::class,'payer_plan_id'); }
    public function service(): BelongsTo { return $this->belongsTo(BillableService::class,'billable_service_id'); }
    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
}
