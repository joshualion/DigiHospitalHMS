<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public function preview(NumberSequence $sequence): string
    {
        return $this->format($sequence, $sequence->next_value);
    }

    public function allocate(NumberSequence $sequence): string
    {
        return DB::transaction(function () use ($sequence): string {
            $locked = NumberSequence::query()
                ->whereKey($sequence->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($locked->status !== 'active', 422, 'The number sequence is inactive.');

            $number = $this->format($locked, $locked->next_value);

            $locked->forceFill([
                'next_value' => $locked->next_value + 1,
                'issued_count' => $locked->issued_count + 1,
            ])->save();

            app(AuditService::class)->record(
                'number_sequences.allocated',
                $locked,
                ['next_value' => $locked->getOriginal('next_value')],
                ['allocated' => $number, 'next_value' => $locked->next_value],
                ['sequence_key' => $locked->key],
            );

            return $number;
        });
    }

    public function format(NumberSequence $sequence, int $value): string
    {
        $parts = [];

        if (filled($sequence->prefix)) {
            $parts[] = $sequence->prefix;
        }

        if (filled($sequence->date_format)) {
            $parts[] = now()->format($sequence->date_format);
        }

        $parts[] = str_pad((string) $value, $sequence->padding_length, '0', STR_PAD_LEFT);

        return implode('-', $parts);
    }

    public function updateConfiguration(NumberSequence $sequence, array $validated): NumberSequence
    {
        if ($sequence->issued_count > 0) {
            unset($validated['prefix'], $validated['date_format'], $validated['padding_length'], $validated['next_value']);
        }

        $before = $sequence->only(array_keys($validated));
        $sequence->update($validated);

        app(AuditService::class)->record('number_sequences.updated', $sequence, $before, $sequence->only(array_keys($validated)));

        return $sequence;
    }
}
