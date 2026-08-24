<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InpatientOrder extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'order_type', 'instruction', 'status', 'ordered_by', 'ordered_at', 'acknowledged_by', 'acknowledged_at', 'completed_by', 'completed_at', 'status_reason'];

    protected function casts(): array
    {
        return ['ordered_at' => 'datetime', 'acknowledged_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }
}
