<?php

namespace App\Services;

use App\Models\SourceRecord;
use App\Models\User;
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

    public function blockCount(?User $user, ?string $sessionId): int
    {
        if (! $user && ! $sessionId) return 0;

        $query = SourceRecord::whereIn('kind', ['post', 'comment', 'live_chat'])
            ->where(fn ($query) => $query->where('metadata->website_community', true)->orWhere('metadata->website_comment', true))
            ->where('metadata->moderation->blocked', true);

        if ($user) return $query->where('metadata->author_user_id', $user->id)->count();

        return $query->where('metadata->author_session_hash', hash('sha256', $sessionId))->count();
    }

    public function blocked(?User $user, ?string $sessionId): bool
    {
        return $this->blockCount($user, $sessionId) >= 3;
    }

    public function adminReasons(array $reasons): array
    {
        return collect($reasons)
            ->map(fn ($reason) => trim((string) $reason))
            ->filter()
            ->isNotEmpty()
            ? [trans('ui.community_ai_reason_generic', [], 'de')]
            : [];
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
