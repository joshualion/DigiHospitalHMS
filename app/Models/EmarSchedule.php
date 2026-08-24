<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmarSchedule extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'prescription_id', 'prescription_item_id', 'medicine_name', 'dose', 'route', 'frequency', 'order_type', 'is_prn', 'prn_instructions', 'scheduled_at', 'status'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'is_prn' => 'boolean'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function administration(): HasOne
    {
        return $this->hasOne(EmarAdministration::class);
    }
}
