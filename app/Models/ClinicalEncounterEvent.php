<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalEncounterEvent extends Model
{
    protected $fillable = ['clinical_encounter_id', 'hospital_id', 'actor_id', 'action', 'from_status', 'to_status', 'before', 'after', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => 'datetime'];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }
}
