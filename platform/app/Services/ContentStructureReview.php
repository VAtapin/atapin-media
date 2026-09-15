<?php

namespace App\Services;

use App\Models\{DesktopAiRequest, SourceRecord, User};
use Illuminate\Support\Facades\DB;

class ContentStructureReview
{
    public const META_KEY = 'structure_review';
    public const SUPPORTED_KINDS = ['post', 'video', 'short'];

    public function version(SourceRecord $record): string
    {
        return hash('sha256', (string) ($record->body ?? ''));
    }

    public function status(SourceRecord $record): array
    {
        $review = ($record->metadata ?? [])[self::META_KEY] ?? [];
        $version = $this->version($record);
        $sameVersion = !empty($review['source_version']) && hash_equals((string) $review['source_version'], $version);
        $state = (string) ($review['status'] ?? 'unreviewed');
        $handled = $sameVersion && in_array($state, ['applied', 'no_change'], true);

        return [
            'status' => $sameVersion ? $state : 'unreviewed',
            'reviewed' => $handled,
            'eligible' => in_array($record->kind, self::SUPPORTED_KINDS, true)
                && !(($record->metadata ?? [])['archive_data'] ?? false)
                && trim(strip_tags((string) $record->body)) !== ''
                && !$handled
                && !($sameVersion && in_array($state, ['queued', 'processing', 'completed'], true)),
            'source_version' => $version,
            'request_id' => $sameVersion ? ($review['request_id'] ?? null) : null,
            'outcome' => $sameVersion ? ($review['outcome'] ?? null) : null,
        ];
    }

    public function queue(User $user, SourceRecord $record, bool $force = false): array
    {
        abort_unless(in_array($record->kind, self::SUPPORTED_KINDS, true), 422);
        abort_unless(!(($record->metadata ?? [])['archive_data'] ?? false), 422);
        abort_unless(trim(strip_tags((string) $record->body)) !== '', 422);

        return DB::transaction(function () use ($user, $record, $force) {
            $record = SourceRecord::lockForUpdate()->findOrFail($record->id);
            $version = $this->version($record);
            $review = ($record->metadata ?? [])[self::META_KEY] ?? [];
            $sameVersion = !empty($review['source_version']) && hash_equals((string) $review['source_version'], $version);
            $stateName = (string) ($review['status'] ?? '');

            if (!$force && $sameVersion && in_array($stateName, ['queued', 'processing', 'completed', 'applied', 'no_change'], true)) {
                return ['status' => 'skipped', 'request_id' => $review['request_id'] ?? null, 'source_version' => $version];
            }

            $entry = app(DesktopAi::class)->submit($user, [
                'question' => 'Nur die Struktur und Absatzformatierung des importierten Textes verbessern. Den Text selbst nicht verändern.',
                'purpose' => 'structure',
                'source_record_id' => $record->id,
                'context' => ['formatting_only' => true],
            ]);
            $metadata = $record->metadata ?? [];
            $metadata[self::META_KEY] = [
                'status' => 'queued',
                'source_version' => $version,
                'request_id' => $entry->id,
                'updated_at' => now()->toIso8601String(),
            ];
            $record->update(['metadata' => $metadata]);

            return ['status' => 'queued', 'request_id' => $entry->id, 'source_version' => $version];
        });
    }

    public function apply(DesktopAiRequest $entry, ?string $proposalVersion = null): void
    {
        abort_unless($entry->purpose === 'structure' && $entry->source_record_id, 422);

        DB::transaction(function () use ($entry, $proposalVersion) {
            $entry = DesktopAiRequest::lockForUpdate()->findOrFail($entry->id);
            abort_unless($entry->status === 'completed', 409);
            abort_if($proposalVersion && !hash_equals($entry->proposalVersion(), $proposalVersion), 409, __('workspaces.ai_proposal_stale'));

            $record = SourceRecord::lockForUpdate()->findOrFail($entry->source_record_id);
            abort_unless(hash_equals((string) $entry->source_version, $this->version($record)), 409, __('workspaces.ai_stale'));

            $formatted = app(RichContent::class)->sanitize((string) (($entry->proposal ?? [])['structured_body'] ?? ''));
            abort_unless(trim(strip_tags($formatted)) !== '', 422);
            abort_unless($this->sameText($record->body ?? '', $formatted), 422, __('imports.structure_text_changed'));
            if ((($record->metadata ?? [])['public_published'] ?? false) && !User::findOrFail($entry->user_id)->hasPermission('content.publish')) {
                abort(403);
            }

            $original = (string) ($record->body ?? '');
            $changed = $original !== $formatted;
            $metadata = $record->metadata ?? [];
            $metadata[self::META_KEY] = [
                ...($metadata[self::META_KEY] ?? []),
                'status' => $changed ? 'applied' : 'no_change',
                'outcome' => $changed ? 'formatted' : 'no_change',
                'request_id' => $entry->id,
                'reviewed_at' => now()->toIso8601String(),
            ];
            if ($changed) {
                $metadata['body_format'] = 'html';
                $record->update(['body' => $formatted, 'metadata' => $metadata]);
            } else {
                $record->update(['metadata' => $metadata]);
            }
            $record->refresh();
            $metadata = $record->metadata ?? [];
            $metadata[self::META_KEY]['source_version'] = $this->version($record);
            $record->updateQuietly(['metadata' => $metadata]);
            $entry->update(['status' => 'applied']);
            app(Audit::class)->record('ai.structure_applied', (string) $entry->id);
        });
    }

    public function markFailed(DesktopAiRequest $entry): void
    {
        if (!$entry->source_record_id) return;
        DB::transaction(function () use ($entry) {
            $record = SourceRecord::lockForUpdate()->find($entry->source_record_id);
            if (!$record) return;
            $metadata = $record->metadata ?? [];
            $metadata[self::META_KEY] = [
                ...($metadata[self::META_KEY] ?? []),
                'status' => 'failed',
                'request_id' => $entry->id,
                'source_version' => $this->version($record),
                'updated_at' => now()->toIso8601String(),
            ];
            $record->updateQuietly(['metadata' => $metadata]);
        });
    }

    private function sameText(string $left, string $right): bool
    {
        $normalize = static function (string $value): string {
            $value = preg_replace('~</?(?:p|h[23]|li|blockquote|div|br)[^>]*>~i', "\n", $value) ?? $value;
            $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        };

        return hash_equals($normalize($left), $normalize($right));
    }
}
