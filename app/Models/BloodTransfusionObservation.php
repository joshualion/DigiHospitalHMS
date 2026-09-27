<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloodTransfusionObservation extends Model
{
    protected $fillable = [
        'blood_transfusion_episode_id', 'recorded_by', 'observed_at', 'temperature_c',
        'pulse_bpm', 'respiratory_rate', 'systolic_bp', 'diastolic_bp', 'spo2_percent', 'notes',
    ];

    protected function casts(): array { return ['observed_at' => 'datetime']; }
    public function episode(): BelongsTo { return $this->belongsTo(BloodTransfusionEpisode::class, 'blood_transfusion_episode_id'); }
}
