<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmarAdministration extends Model
{
    protected $fillable = ['hospital_id', 'facility_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'emar_schedule_id', 'prescription_id', 'prescription_item_id', 'prescription_dispense_id', 'inventory_batch_id', 'medicine_name', 'dose', 'route', 'scheduled_at', 'actual_at', 'quantity_administered', 'outcome', 'confirmation', 'reason', 'prn_indication', 'prn_response', 'administered_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'actual_at' => 'datetime', 'quantity_administered' => 'decimal:4', 'confirmation' => 'array'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(EmarSchedule::class, 'emar_schedule_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function dispense(): BelongsTo
    {
        return $this->belongsTo(PrescriptionDispense::class, 'prescription_dispense_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(EmarAmendment::class);
    }
}
