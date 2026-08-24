<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodScreeningResult extends Model
{
    protected $fillable = ['hospital_id', 'blood_donation_id', 'blood_screening_test_id', 'lab_specimen_id', 'lab_result_id', 'result_value', 'release_cleared', 'status', 'notes', 'entered_by', 'entered_at', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['release_cleared' => 'boolean', 'entered_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(BloodScreeningTest::class, 'blood_screening_test_id');
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(BloodDonation::class, 'blood_donation_id');
    }
}
