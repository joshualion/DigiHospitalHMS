<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InpatientDiagnosis extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'description', 'coding_system', 'code', 'status', 'recorded_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }
}
