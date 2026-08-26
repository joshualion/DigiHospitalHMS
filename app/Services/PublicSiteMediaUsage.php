<?php

namespace App\Services;

use App\Models\PublicSiteItem;
use App\Models\PublicSiteMedia;
use App\Models\PublicSitePage;
use Illuminate\Support\Facades\Storage;

class PublicSiteMediaUsage
{
    public function refreshHospital(int $hospitalId): void
    {
        $references = $this->references($hospitalId);

        PublicSiteMedia::where('hospital_id', $hospitalId)->get()->each(function (PublicSiteMedia $media) use ($references): void {
            $values = array_filter([
                $media->path,
                Storage::disk($media->disk)->url($media->path),
                asset(Storage::disk($media->disk)->url($media->path)),
                $media->url,
            ]);

            $usageCount = collect($values)
                ->unique()
                ->sum(fn (string $value): int => $references->filter(fn (string $reference): bool => $reference === $value)->count());

            if ($media->usage_count !== $usageCount) {
                $media->forceFill(['usage_count' => $usageCount])->save();
            }
        });
    }

    private function references(int $hospitalId)
    {
        $payloads = collect();

        PublicSitePage::where('hospital_id', $hospitalId)->get()->each(function (PublicSitePage $page) use ($payloads): void {
            $payloads->push($page->draft_content, $page->published_content, $page->draft_seo, $page->published_seo);

            $page->sections()->get()->each(function ($section) use ($payloads): void {
                $payloads->push($section->draft_content, $section->published_content);
            });
        });

        PublicSiteItem::where('hospital_id', $hospitalId)->get()->each(function (PublicSiteItem $item) use ($payloads): void {
            $payloads->push($item->draft_content, $item->published_content);
        });

        return $payloads->flatMap(fn ($payload): array => $this->strings($payload))->values();
    }

    private function strings(mixed $payload): array
    {
        if (is_string($payload)) {
            return [$payload];
        }

        if (! is_array($payload)) {
            return [];
        }

        $strings = [];

        foreach ($payload as $value) {
            array_push($strings, ...$this->strings($value));
        }

        return $strings;
    }
}
