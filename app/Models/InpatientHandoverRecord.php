<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InpatientHandoverRecord extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'from_shift', 'to_shift', 'summary', 'status', 'authored_by', 'authored_at', 'acknowledged_by', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }
}
