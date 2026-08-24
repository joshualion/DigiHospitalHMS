<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InpatientProgressNote extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'admission_id', 'patient_id', 'note_type', 'subjective', 'objective', 'assessment', 'plan', 'narrative', 'status', 'authored_by', 'authored_at', 'signed_by', 'signed_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime', 'signed_at' => 'datetime'];
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(InpatientChart::class, 'inpatient_chart_id');
    }

    public function amendments(): MorphMany
    {
        return $this->morphMany(InpatientAmendment::class, 'amendable');
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }
}
