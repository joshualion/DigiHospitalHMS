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
                'published_content' => $payload,
                'published_version' => $version,
                'published_at' => now(),
                'published_by' => $actor->id,
            ];

            if (! $model instanceof PublicSiteSection) {
                $published['status'] = 'published';
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
                $model->forceFill([
                    'status' => 'draft',
                    'unpublished_at' => now(),
                ])->save();
            }

            $version = ((int) PublicSiteRevision::where('revisionable_type', $model::class)
                ->where('revisionable_id', $model->getKey())
                ->max('version')) + 1;

            PublicSiteRevision::create([
                'hospital_id' => $this->hospitalId($model),
                'revisionable_type' => $model::class,
                'revisionable_id' => $model->getKey(),
                'version' => $version,
                'payload' => $model->published_content ?? [],
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

            $model->forceFill([
                'draft_content' => $revision->payload,
            ])->save();

            $this->audit->record('website.revision_restored', $model, $before, $model->fresh()->toArray(), [
                'restored_revision_id' => $revision->id,
                'restored_version' => $revision->version,
            ], actor: $actor);
        });
    }

    private function draftPayload(Model $model): array
    {
        return match (true) {
            $model instanceof PublicSitePage => $model->draft_content ?? [],
            $model instanceof PublicSiteSection => $model->draft_content ?? [],
            $model instanceof PublicSiteItem => $model->draft_content ?? [],
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
