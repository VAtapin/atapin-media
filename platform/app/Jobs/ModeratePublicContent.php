<?php

namespace App\Jobs;

use App\Models\SourceRecord;
use App\Services\{Audit, PublicAiModerator, PublicCommunityModeration};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ModeratePublicContent implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $timeout = 90;
    public int $tries = 1;
    public int $uniqueFor = 600;

    public function __construct(public int $recordId) {}

    public function uniqueId(): string
    {
        return (string) $this->recordId;
    }

    public function handle(PublicAiModerator $moderator, PublicCommunityModeration $queue, Audit $audit): void
    {
        $record = SourceRecord::find($this->recordId);
        if (! $record || ! $queue->isModeratable($record) || ($record->metadata['moderation']['state'] ?? null) !== 'pending_ai') return;

        if (! $moderator->available()) {
            $this->sendToHuman($record, 'ai_unavailable', $audit);
            return;
        }

        try {
            $result = $moderator->moderate([
                'kind' => $record->kind,
                'title' => $record->title,
                'body' => $record->body,
                'author_type' => $record->metadata['author_type'] ?? 'unknown',
            ]);
        } catch (Throwable $exception) {
            $this->sendToHuman($record, 'ai_error', $audit);
            return;
        }

        DB::transaction(function () use ($record, $result) {
            $fresh = SourceRecord::lockForUpdate()->findOrFail($record->id);
            if (($fresh->metadata['moderation']['state'] ?? null) !== 'pending_ai') return;
            $allow = $result['verdict'] === 'allow';
            $metadata = $fresh->metadata ?? [];
            $metadata['public_published'] = $allow;
            $metadata['public_published_at'] = $allow ? now()->toIso8601String() : null;
            $metadata['moderation'] = [
                ...($metadata['moderation'] ?? []),
                'state' => $allow ? 'published' : 'human_review',
                'verdict' => $result['verdict'],
                'risk' => (float) $result['risk'],
                'reasons' => array_values($result['reasons']),
                'provider' => 'openai',
                'model' => app(\App\Services\Settings::class)->get('ai_model'),
                'checked_at' => now()->toIso8601String(),
                'blocked' => ! $allow,
            ];
            $fresh->update(['status' => $allow ? 'ready' : 'needs_attention', 'metadata' => $metadata]);
        });

        $audit->record($result['verdict'] === 'allow' ? 'community.ai_approved' : 'community.ai_escalated', (string) $record->id, [
            'risk' => (float) $result['risk'],
        ]);
    }

    private function sendToHuman(SourceRecord $record, string $reason, Audit $audit): void
    {
        DB::transaction(function () use ($record, $reason) {
            $fresh = SourceRecord::lockForUpdate()->findOrFail($record->id);
            $metadata = $fresh->metadata ?? [];
            $metadata['moderation'] = [
                ...($metadata['moderation'] ?? []),
                'state' => 'human_review',
                'verdict' => 'review',
                'risk' => 1.0,
                'reasons' => [$reason],
                'checked_at' => now()->toIso8601String(),
                'blocked' => false,
            ];
            $fresh->update(['status' => 'needs_attention', 'metadata' => $metadata, 'updated_at' => now()]);
        });
        $audit->record('community.ai_escalated', (string) $record->id, ['reason' => $reason]);
    }
}
