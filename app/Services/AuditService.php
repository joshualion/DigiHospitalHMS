<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'session',
        'secret',
        'api_key',
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
        ?string $reason = null,
        ?Hospital $hospital = null,
        ?Facility $facility = null,
        ?User $actor = null,
        ?Request $request = null,
    ): AuditEvent {
        $request ??= request();
        $actor ??= $request?->user();

        return AuditEvent::create([
            'hospital_id' => $hospital?->id ?? $this->hospitalId($subject, $actor),
            'facility_id' => $facility?->id ?? $this->facilityId($subject),
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'request_id' => $request?->headers->get('X-Request-Id') ?: (string) Str::uuid(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'metadata' => $this->redact($metadata),
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    public function changes(Model $model): array
    {
        return [
            'before' => Arr::only($model->getOriginal(), array_keys($model->getChanges())),
            'after' => $model->getChanges(),
        ];
    }

    public function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        foreach ($payload as $key => $value) {
            if (in_array(Str::lower((string) $key), self::SENSITIVE_KEYS, true)) {
                $payload[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redact($value);
            }
        }

        return $payload;
    }

    private function hospitalId(?Model $subject, ?User $actor): ?int
    {
        if ($subject && isset($subject->hospital_id)) {
            return $subject->hospital_id;
        }

        if ($subject instanceof Hospital) {
            return $subject->id;
        }

        return $actor?->hospitalId();
    }

    private function facilityId(?Model $subject): ?int
    {
        if ($subject && isset($subject->facility_id)) {
            return $subject->facility_id;
        }

        if ($subject instanceof Facility) {
            return $subject->id;
        }

        return null;
    }
}
