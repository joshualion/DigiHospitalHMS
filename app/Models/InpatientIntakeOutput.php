<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InpatientIntakeOutput extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'direction', 'measurement_type', 'quantity', 'unit', 'notes', 'measured_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }
}
