<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdmissionEvent extends Model
{
    protected $fillable = ['hospital_id', 'subject_type', 'subject_id', 'actor_id', 'action', 'before', 'after', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'occurred_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
