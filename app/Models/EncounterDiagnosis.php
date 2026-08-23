<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncounterDiagnosis extends Model
{
    protected $fillable = ['clinical_encounter_id', 'hospital_id', 'description', 'coding_system', 'code', 'status', 'recorded_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }
}
