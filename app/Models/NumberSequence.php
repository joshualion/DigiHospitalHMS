<?php

namespace App\Models;

use App\Services\NumberSequenceService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'facility_id',
        'key',
        'label',
        'prefix',
        'date_format',
        'padding_length',
        'next_value',
        'issued_count',
        'status',
    ];

    protected $appends = [
        'preview',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    protected function preview(): Attribute
    {
        return Attribute::get(fn (): string => app(NumberSequenceService::class)->preview($this));
    }
}
