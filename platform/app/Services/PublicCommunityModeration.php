<?php

namespace App\Services;

use App\Models\SourceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PublicCommunityModeration
{
    public function isModeratable(SourceRecord $record): bool
    {
        return (bool) ($record->metadata['website_community'] ?? false)
            || (bool) ($record->metadata['website_comment'] ?? false);
    }

    public function pending(): Builder
    {
        return SourceRecord::where('status', 'needs_attention')
            ->where(fn ($query) => $query->where('metadata->website_community', true)->orWhere('metadata->website_comment', true))
            ->where(fn ($query) => $query->where('metadata->moderation->state', 'human_review')->orWhereNull('metadata->moderation->state'));
    }

    public function aiPending(): Builder
    {
        return SourceRecord::where('status', 'needs_attention')
            ->where('metadata->moderation->state', 'pending_ai');
    }

    public function stats(): array
    {
        return ['human_review' => $this->pending()->count(), 'ai_pending' => $this->aiPending()->count()];
    }

    public function decide(SourceRecord $record, string $decision, int $userId): void
    {
        abort_unless($this->isModeratable($record), 404);
        DB::transaction(function () use ($record, $decision, $userId) {
            $fresh = SourceRecord::lockForUpdate()->findOrFail($record->id);
            $publish = $decision === 'publish';
            $metadata = $fresh->metadata ?? [];
            $metadata['public_published'] = $publish;
            $metadata['public_published_at'] = $publish ? now()->toIso8601String() : null;
            $metadata['moderation'] = [
                ...($metadata['moderation'] ?? []),
                'state' => $publish ? 'published' : 'rejected',
                'decision' => $decision,
                'moderated_by' => $userId,
                'moderated_at' => now()->toIso8601String(),
            ];
            $metadata['moderation_rejected'] = ! $publish;
            $fresh->update(['status' => $publish ? 'ready' : 'needs_attention', 'metadata' => $metadata]);
        });
    }
}
