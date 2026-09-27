<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodTransfusionReaction extends Model
{
    protected $fillable = [
        'blood_transfusion_episode_id', 'reported_by', 'occurred_at', 'reported_severity',
        'observed_signs', 'immediate_actions', 'clinician_notified_at', 'blood_bank_notified_at',
        'status', 'resolved_by', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime', 'clinician_notified_at' => 'datetime',
            'blood_bank_notified_at' => 'datetime', 'resolved_at' => 'datetime',
        ];
    }

    public function episode(): BelongsTo { return $this->belongsTo(BloodTransfusionEpisode::class, 'blood_transfusion_episode_id'); }
}
