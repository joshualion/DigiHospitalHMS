<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'facility_id',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'request_id',
        'ip_address',
        'user_agent',
        'before',
        'after',
        'metadata',
        'reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
