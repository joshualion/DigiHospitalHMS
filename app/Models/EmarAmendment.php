<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmarAmendment extends Model
{
    protected $fillable = ['hospital_id', 'emar_administration_id', 'reason', 'content', 'authored_by', 'authored_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }

    public function administration(): BelongsTo
    {
        return $this->belongsTo(EmarAdministration::class, 'emar_administration_id');
    }
}
