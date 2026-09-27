<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayerOrganization extends Model
{
    protected $fillable = ['hospital_id','type','code','name','contact_name','email','phone','address','credit_days','status','notes'];
    public function plans(): HasMany { return $this->hasMany(PayerPlan::class); }
    public function coverages(): HasMany { return $this->hasMany(PatientCoverage::class); }
}
