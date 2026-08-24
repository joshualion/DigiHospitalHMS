<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodDonorScreening extends Model
{
    protected $fillable = ['hospital_id', 'blood_donor_id', 'recorded_by', 'responses', 'eligibility_status', 'decision_reason', 'decided_at'];

    protected function casts(): array
    {
        return ['responses' => 'array', 'decided_at' => 'datetime'];
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(BloodDonor::class, 'blood_donor_id');
    }
}
