<?php

namespace App\Services;

use App\Models\PublicSiteItem;
use App\Models\PublicSitePage;
use App\Models\PublicSiteRevision;
use App\Models\PublicSiteSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PublicSitePublisher
{
    public function __construct(private readonly AuditService $audit) {}

    public function publish(Model $model, User $actor, ?string $note = null): PublicSiteRevision
    {
        return DB::transaction(function () use ($model, $actor, $note): PublicSiteRevision {
            $before = $model->toArray();
            $version = (int) ($model->published_version ?? 0) + 1;
            $payload = $this->draftPayload($model);

            $published = [
                'published_content' => $payload['content'] ?? [],
                'published_version' => $version,
                'published_at' => now(),
                'published_by' => $actor->id,
            ];

            if ($model instanceof PublicSitePage) {
                $published['title'] = $payload['title'];
                $published['published_title'] = $payload['title'];
                $published['published_seo'] = $payload['seo'] ?? [];
            }

            if ($model instanceof PublicSiteSection) {
                $published['label'] = $payload['label'];
                $published['published_label'] = $payload['label'];
                $published['sort_order'] = $payload['sort_order'];
                $published['published_sort_order'] = $payload['sort_order'];
                $published['is_enabled'] = $payload['is_enabled'];
                $published['published_is_enabled'] = $payload['is_enabled'];
            }

            if ($model instanceof PublicSiteItem) {
                $published['public_site_section_id'] = $payload['public_site_section_id'];
                $published['published_public_site_section_id'] = $payload['public_site_section_id'];
                $published['type'] = $payload['type'];
                $published['published_type'] = $payload['type'];
                $published['slug'] = $payload['slug'];
                $published['published_slug'] = $payload['slug'];
                $published['title'] = $payload['title'];
                $published['published_title'] = $payload['title'];
                $published['summary'] = $payload['summary'];
                $published['published_summary'] = $payload['summary'];
                $published['is_enabled'] = $payload['is_enabled'];
                $published['published_is_enabled'] = $payload['is_enabled'];
                $published['is_featured'] = $payload['is_featured'];
                $published['published_is_featured'] = $payload['is_featured'];
                $published['sort_order'] = $payload['sort_order'];
                $published['published_sort_order'] = $payload['sort_order'];
            }

            if (! $model instanceof PublicSiteSection) {
                $published['status'] = 'published';
            }

            if ($model instanceof PublicSitePage) {
                $published['unpublished_at'] = null;
            }

            $model->forceFill($published)->save();

            $revision = PublicSiteRevision::create([
                'hospital_id' => $this->hospitalId($model),
                'revisionable_type' => $model::class,
                'revisionable_id' => $model->getKey(),
                'version' => $version,
                'payload' => $payload,
                'action' => 'publish',
                'note' => $note,
                'created_by' => $actor->id,
                'published_at' => now(),
            ]);

            $this->audit->record('website.published', $model, $before, $model->fresh()->toArray(), [
                'version' => $version,
                'revision_id' => $revision->id,
            ], hospital: $model instanceof PublicSitePage ? $model->hospital : null, actor: $actor);

            return $revision;
        });
    }

    public function unpublish(Model $model, User $actor): void
    {
        DB::transaction(function () use ($model, $actor): void {
            $before = $model->toArray();

            if (! $model instanceof PublicSiteSection) {
                $updates = ['status' => 'draft'];

                if ($model instanceof PublicSitePage) {
                    $updates['unpublished_at'] = now();
                }

                $model->forceFill($updates)->save();
            }

            $version = ((int) PublicSiteRevision::where('revisionable_type', $model::class)
                ->where('revisionable_id', $model->getKey())
                ->max('version')) + 1;

            PublicSiteRevision::create([
                'hospital_id' => $this->hospitalId($model),
                'revisionable_type' => $model::class,
                'revisionable_id' => $model->getKey(),
                'version' => $version,
                'payload' => $this->publishedPayload($model),
                'action' => 'unpublish',
                'created_by' => $actor->id,
            ]);

            $this->audit->record('website.unpublished', $model, $before, $model->fresh()->toArray(), actor: $actor);
        });
    }

    public function restore(PublicSiteRevision $revision, User $actor): void
    {
        DB::transaction(function () use ($revision, $actor): void {
            $model = $revision->revisionable;
            $before = $model->toArray();

            $model->forceFill($this->restorePayload($model, $revision->payload))->save();

            $this->audit->record('website.revision_restored', $model, $before, $model->fresh()->toArray(), [
                'restored_revision_id' => $revision->id,
                'restored_version' => $revision->version,
            ], actor: $actor);
        });
    }

    private function draftPayload(Model $model): array
    {
        return match (true) {
            $model instanceof PublicSitePage => $model->draftSnapshot(),
            $model instanceof PublicSiteSection => $model->draftSnapshot(),
            $model instanceof PublicSiteItem => $model->draftSnapshot(),
            default => [],
        };
    }

    private function publishedPayload(Model $model): array
    {
        return match (true) {
            $model instanceof PublicSitePage => $model->publishedSnapshot(),
            $model instanceof PublicSiteSection => $model->publishedSnapshot(),
            $model instanceof PublicSiteItem => $model->publishedSnapshot(),
            default => [],
        };
    }

    private function restorePayload(Model $model, array $payload): array
    {
        return match (true) {
            $model instanceof PublicSitePage => [
                'draft_title' => $payload['title'] ?? $model->draft_title ?? $model->title,
                'draft_content' => $payload['content'] ?? [],
                'draft_seo' => $payload['seo'] ?? [],
            ],
            $model instanceof PublicSiteSection => [
                'draft_label' => $payload['label'] ?? $model->draft_label ?? $model->label,
                'draft_sort_order' => $payload['sort_order'] ?? $model->draft_sort_order ?? $model->sort_order,
                'draft_is_enabled' => $payload['is_enabled'] ?? $model->draft_is_enabled ?? $model->is_enabled,
                'draft_content' => $payload['content'] ?? [],
            ],
            $model instanceof PublicSiteItem => [
                'draft_public_site_section_id' => $payload['public_site_section_id'] ?? $model->draft_public_site_section_id ?? $model->public_site_section_id,
                'draft_type' => $payload['type'] ?? $model->draft_type ?? $model->type,
                'draft_slug' => $payload['slug'] ?? $model->draft_slug ?? $model->slug,
                'draft_title' => $payload['title'] ?? $model->draft_title ?? $model->title,
                'draft_summary' => $payload['summary'] ?? $model->draft_summary ?? $model->summary,
                'draft_content' => $payload['content'] ?? [],
                'draft_is_enabled' => $payload['is_enabled'] ?? $model->draft_is_enabled ?? $model->is_enabled,
                'draft_is_featured' => $payload['is_featured'] ?? $model->draft_is_featured ?? $model->is_featured,
                'draft_sort_order' => $payload['sort_order'] ?? $model->draft_sort_order ?? $model->sort_order,
            ],
            default => [],
        };
    }

    private function hospitalId(Model $model): int
    {
        if ($model instanceof PublicSiteSection) {
            return $model->page->hospital_id;
        }

        return (int) $model->hospital_id;
    }
}
