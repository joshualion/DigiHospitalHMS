<?php

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\PublicSiteItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        PublicSiteItem::query()
            ->where(fn ($query) => $query->where('type', 'service')->orWhere('draft_type', 'service')->orWhere('published_type', 'service'))
            ->orderBy('id')
            ->each(function (PublicSiteItem $item): void {
                $type = $item->published_type ?? $item->draft_type ?? $item->type;

                if ($type !== 'service') {
                    return;
                }

                $content = $item->published_content ?: $item->draft_content ?: [];
                $title = $item->published_title ?: $item->draft_title ?: $item->title ?: 'Service';
                $slug = $item->published_slug ?: $item->draft_slug ?: $item->slug ?: Str::slug($title);
                $summary = $item->published_summary ?: $item->draft_summary ?: $item->summary;
                $description = $content['description'] ?? $summary;
                $isVisible = (bool) ($item->published_is_enabled ?? $item->draft_is_enabled ?? $item->is_enabled);
                $isFeatured = (bool) ($item->published_is_featured ?? $item->draft_is_featured ?? $item->is_featured);

                if (BillableService::where('hospital_id', $item->hospital_id)->where('public_slug', $slug)->exists()) {
                    $this->hideLegacyItem($item);

                    return;
                }

                BillableService::create([
                    'hospital_id' => $item->hospital_id,
                    'billable_service_category_id' => $this->defaultCategory($item->hospital_id)->id,
                    'code' => $this->uniqueCode($item->hospital_id, $title),
                    'name' => $title,
                    'description' => $description,
                    'is_tax_exempt' => true,
                    'tax_rate_basis_points' => 0,
                    'is_discount_eligible' => true,
                    'is_active' => $isVisible,
                    'public_is_visible' => $isVisible,
                    'public_is_featured' => $isFeatured,
                    'public_slug' => $slug,
                    'public_name' => $title,
                    'public_description' => $description,
                    'public_icon' => $content['icon'] ?? 'stethoscope',
                    'public_image_path' => $content['image'] ?? null,
                    'public_display_order' => (int) ($item->published_sort_order ?? $item->draft_sort_order ?? $item->sort_order ?? 0),
                ]);

                $this->hideLegacyItem($item);
            });
    }

    public function down(): void
    {
        //
    }

    private function defaultCategory(int $hospitalId): BillableServiceCategory
    {
        return BillableServiceCategory::firstOrCreate(
            ['hospital_id' => $hospitalId, 'code' => 'PUBLIC'],
            ['name' => 'Public services', 'description' => 'Default category for public website service records.', 'is_active' => true],
        );
    }

    private function uniqueCode(int $hospitalId, string $title): string
    {
        $base = Str::upper(Str::slug($title, '_')) ?: 'SERVICE';
        $base = Str::limit($base, 32, '');
        $candidate = $base;
        $suffix = 2;

        while (BillableService::where('hospital_id', $hospitalId)->where('code', $candidate)->exists()) {
            $candidate = Str::limit($base, 35 - strlen((string) $suffix), '')."_{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function hideLegacyItem(PublicSiteItem $item): void
    {
        $item->forceFill([
            'is_enabled' => false,
            'draft_is_enabled' => false,
            'published_is_enabled' => false,
            'is_featured' => false,
            'draft_is_featured' => false,
            'published_is_featured' => false,
            'status' => 'draft',
        ])->save();
    }
};
