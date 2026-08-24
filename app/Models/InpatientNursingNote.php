<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InpatientNursingNote extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'shift', 'note', 'status', 'authored_by', 'authored_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }
}
