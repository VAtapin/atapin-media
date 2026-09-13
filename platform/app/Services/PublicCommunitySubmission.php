<?php

namespace App\Services;

use App\Jobs\ModeratePublicContent;
use App\Models\{SourceRecord, User};
use Illuminate\Support\Str;

class PublicCommunitySubmission
{
    public function post(?User $user, array $data, ?string $sessionId = null): SourceRecord
    {
        return $this->create($user, 'post', $data['title'], $data['body'], [
            'public_section' => 'community',
            'website_community' => true,
            'community_type' => $data['type'],
        ], $sessionId);
    }

    public function message(?User $user, SourceRecord $parent, string $body, string $kind, ?string $sessionId = null): SourceRecord
    {
        return $this->create($user, $kind, Str::limit($body, 120, ''), $body, [
            'parent_source_id' => $parent->source_id,
            'website_comment' => true,
        ], $sessionId);
    }

    private function create(?User $user, string $kind, string $title, string $body, array $metadata, ?string $sessionId): SourceRecord
    {
        $author = $user?->name ?: __('public.guest');
        $record = SourceRecord::create([
            'source' => 'website',
            'source_id' => 'website-community:'.Str::uuid(),
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'status' => 'needs_attention',
            'metadata' => [
                ...$metadata,
                'author' => $author,
                'author_type' => $user ? 'user' : 'guest',
                'author_user_id' => $user?->id,
                'author_session_hash' => $sessionId ? hash('sha256', $sessionId) : null,
                'public_published' => false,
                'moderation' => ['state' => 'pending_ai'],
            ],
        ]);

        app(Audit::class)->record('community.submitted', (string) $record->id, [
            'author_type' => $user ? 'user' : 'guest',
            'kind' => $kind,
        ]);
        ModeratePublicContent::dispatch($record->id)->afterCommit();

        return $record;
    }
}
