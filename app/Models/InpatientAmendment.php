<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InpatientAmendment extends Model
{
    protected $fillable = ['hospital_id', 'inpatient_chart_id', 'amendable_type', 'amendable_id', 'reason', 'content', 'authored_by', 'authored_at'];

    protected function casts(): array
    {
        return ['authored_at' => 'datetime'];
    }

    public function amendable(): MorphTo
    {
        return $this->morphTo();
    }
}
