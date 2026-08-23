<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncounterAmendment extends Model
{
    protected $fillable = ['clinical_encounter_id', 'hospital_id', 'reason', 'content', 'authored_by', 'authored_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }
}
