<?php

namespace App\Services;

use Illuminate\Support\Str;

class SensitiveLookup
{
    public function hash(?string $value): ?string
    {
        $normalized = $this->normalize($value);

        if ($normalized === null) {
            return null;
        }

        return hash_hmac('sha256', $normalized, config('app.key'));
    }

    public function normalize(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return Str::of($value)->lower()->replaceMatches('/\s+/', '')->trim()->value();
    }
}
