<?php

namespace App\Services\Publishing;

use App\Models\{Publication, SourceRecord};
use Illuminate\Support\Facades\DB;

class PublishingService
{
    public function __construct(private readonly ConnectorRegistry $registry, private readonly ConnectionStore $connections)
    {
    }

    /** @return array<int, string> */
    public function defaultProviders(SourceRecord $record): array
    {
        $providers = ['website'];
        foreach ($this->connections->publicConnections() as $connection) {
            if ($connection['connected'] && ! $connection['revoked']) $providers[] = $connection['provider'];
        }
        return array_values(array_unique($providers));
    }

    /** @param array<int, string>|null $providers */
    public function queueForRecord(SourceRecord $record, ?array $providers = null): void
    {
        if ($record->status !== 'ready' || ($record->metadata['archive_data'] ?? false) || ($record->metadata['library_only'] ?? false)) return;
        $providers ??= $this->defaultProviders($record);
        $providers = array_values(array_unique(array_filter($providers, 'is_string')));
        if ($record->metadata['public_published'] ?? false) $this->queueChanges($record);
        if ((($record->metadata ?? [])['public_published'] ?? false) && in_array('website', $providers, true)) {
            Publication::updateOrCreate(
                ['source_record_id' => $record->id, 'provider' => 'website', 'direction' => 'outbound'],
                ['status' => 'published', 'published_at' => now(), 'remote_status' => 'published', 'error' => null]
            );
        }
        if (! in_array($record->kind, ['video', 'short', 'post', 'live'], true)) return;
        foreach (array_diff($providers, ['website']) as $provider) {
            $publication = Publication::firstOrNew(['source_record_id' => $record->id, 'provider' => $provider, 'direction' => 'outbound']);
            if ($publication->exists && $publication->remote_status === 'deleted') $publication->fill(['status' => 'new', 'external_id' => null, 'external_url' => null, 'remote_status' => null, 'published_at' => null, 'payload' => []]);
            if ($publication->exists && in_array($publication->status, ['queued', 'processing', 'published'], true)) continue;
            $publication->fill(['status' => 'queued', 'error' => null, 'next_attempt_at' => null, 'payload' => [...$this->payload($record, $provider), ...($publication->payload ?? [])]]);
            $publication->save();
            \App\Jobs\PublishToPlatform::dispatch($publication->id)->afterCommit();
        }
    }

    public function execute(int $publicationId): void
    {
        $claimed = Publication::whereKey($publicationId)->where('direction', 'outbound')->where('status', 'queued')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update(['status' => 'processing', 'attempts' => DB::raw('attempts + 1'), 'last_attempt_at' => now(), 'error' => null]);
        if (! $claimed) return;
        $publication = Publication::with('record')->findOrFail($publicationId);
        try {
            if (empty($publication->payload['requested_signature']) && $publication->record) $publication->update(['payload' => [...($publication->payload ?? []), 'requested_signature' => $this->signature($publication->record)]]);
            if (! $this->connections->connected($publication->provider)) throw new UnsupportedCapability('The destination is disconnected.');
            $connector = $this->registry->get($publication->provider);
            $action = $publication->payload['action'] ?? null;
            if ($action && ! $connector instanceof \App\Contracts\ManagesPublications) throw new UnsupportedCapability('This platform does not support the requested update or visibility operation.');
            $result = $action ? $connector->manage($publication->fresh('record'), $action) : $connector->publish($publication->fresh('record'));
            $publication->refresh();
            $publication->update([
                'status' => ($result['remote_status'] ?? null) === 'deleted' ? 'deleted' : 'published',
                'external_id' => $result['external_id'] ?? $publication->external_id,
                'external_url' => $result['external_url'] ?? $publication->external_url,
                'remote_status' => $result['remote_status'] ?? 'published',
                'payload' => [...($publication->payload ?? []), ...($result['payload'] ?? []), 'action' => null, 'consecutive_failures' => 0, 'applied_signature' => $publication->payload['requested_signature'] ?? null],
                'published_at' => $publication->published_at ?? now(),
                'next_attempt_at' => null,
            ]);
            $record = $publication->record?->fresh();
            if ($record?->publishingKind() === 'live' && ($record->metadata['live_status'] ?? null) === 'ended') {
                $this->queueLiveCompletion($record);
            }
            if ($record && $action !== 'delete') $this->queueChanges($record);
        } catch (PublicationPending) {
            $publication->refresh();
            $publication->update(['status' => 'queued', 'remote_status' => 'processing', 'payload' => [...($publication->payload ?? []), 'consecutive_failures' => 0], 'next_attempt_at' => now()->addSeconds(30)]);
        } catch (UnsupportedCapability $error) {
            $publication->update(['status' => 'skipped', 'remote_status' => 'unsupported', 'error' => $error->getMessage(), 'next_attempt_at' => null]);
        } catch (\Throwable $error) {
            $this->fail($publicationId, $error);
        }
    }

    public function retry(Publication $publication): bool
    {
        if (! $this->connections->connected($publication->provider)) return false;
        if (! Publication::whereKey($publication->id)->where('direction', 'outbound')->where('status', 'failed')
            ->update(['status' => 'queued', 'error' => null, 'next_attempt_at' => null])) return false;
        \App\Jobs\PublishToPlatform::dispatch($publication->id)->afterCommit();
        return true;
    }

    public function fail(int $publicationId, \Throwable $error): void
    {
        $publication = Publication::find($publicationId);
        if (! $publication || $publication->status !== 'processing') return;
        $failures = ($publication->payload['consecutive_failures'] ?? 0) + 1;
        $publication->update(['status' => 'failed', 'error' => $this->connections->safeError($error), 'payload' => [...($publication->payload ?? []), 'consecutive_failures' => $failures],
            'next_attempt_at' => $failures < 5 ? now()->addMinutes(min(60, $failures * 5)) : null]);
    }

    public function dispatchDue(): int
    {
        $count = 0;
        Publication::where('direction', 'outbound')->where('status', 'failed')->where('next_attempt_at', '<=', now())
            ->chunkById(100, function ($publications) use (&$count) {
                foreach ($publications as $publication) if ($this->retry($publication)) $count++;
            });
        Publication::where('direction', 'outbound')->where('status', 'queued')->where('next_attempt_at', '<=', now())
            ->chunkById(100, function ($publications) use (&$count) {
                foreach ($publications as $publication) {
                    if (! $this->connections->connected($publication->provider)) continue;
                    if (! Publication::whereKey($publication->id)->where('status', 'queued')->where('next_attempt_at', '<=', now())
                        ->update(['next_attempt_at' => null])) continue;
                    \App\Jobs\PublishToPlatform::dispatch($publication->id)->afterCommit();
                    $count++;
                }
            });
        return $count;
    }

    public function queueLiveCompletion(SourceRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $publication = Publication::where('source_record_id', $record->id)->where('provider', 'youtube')
                ->where('direction', 'outbound')->lockForUpdate()->first();
            if (! $publication || $publication->status !== 'published' || empty($publication->payload['broadcast_id'])
                || in_array($publication->remote_status, ['ended', 'not_started'], true)) return;
            $publication->update(['status' => 'queued', 'payload' => [...($publication->payload ?? []), 'live_end_requested' => true]]);
            \App\Jobs\PublishToPlatform::dispatch($publication->id)->afterCommit();
        });
    }

    public function payload(SourceRecord $record, string $provider): array
    {
        return ['title' => $record->title, 'kind' => $record->publishingKind(), 'requested_at' => now()->toIso8601String(), 'provider' => $provider, 'requested_signature' => $this->signature($record)];
    }

    public function signature(SourceRecord $record): string
    {
        $meta = $record->metadata ?? [];
        return hash('sha256', json_encode([$record->title, $record->body, $meta['tags'] ?? $meta['original_tags'] ?? [], $meta['cover_media_id'] ?? null,
            $record->status === 'ready' && ! $record->trashed() && ($meta['public_published'] ?? false)], JSON_THROW_ON_ERROR));
    }

    public function queueChanges(SourceRecord $record): void
    {
        $signature = $this->signature($record);
        $removeRequested = ($record->metadata['remove_external_on_unpublish'] ?? false) && ($record->trashed() || $record->status !== 'ready' || ! ($record->metadata['public_published'] ?? false));
        foreach (Publication::where('source_record_id', $record->id)->where('direction', 'outbound')->whereNotNull('external_id')->get() as $publication) {
            if ($publication->provider === 'website' || str_starts_with($publication->provider, 'rtmp_') || (($publication->payload['origin'] ?? null) === 'youtube_sync' && ! $removeRequested)
                || ($publication->payload['applied_signature'] ?? null) === $signature || in_array($publication->remote_status, ['deleted', 'ended', 'not_started'], true)
                || in_array($publication->status, ['queued', 'processing'], true) || ($publication->status === 'failed' && empty($publication->payload['action']))) continue;
            // Missing signatures belong to pre-upgrade publications: a source edit still updates them.
            $action = $record->status === 'ready' && ! $record->trashed() && ($record->metadata['public_published'] ?? false) ? 'update' : 'hide';
            if ($action === 'hide' && ($record->metadata['remove_external_on_unpublish'] ?? false)) $action = 'delete';
            try { $connector = $this->registry->get($publication->provider); } catch (UnsupportedCapability) { continue; }
            if (! $connector instanceof \App\Contracts\ManagesPublications || ! in_array($action, $connector->actions(), true)) continue;
            DB::transaction(function () use ($publication, $action, $signature) {
                $current = Publication::whereKey($publication->id)->lockForUpdate()->first();
                if (in_array($current->status, ['queued', 'processing'], true)) return;
                $current->update(['status' => 'queued', 'error' => null, 'next_attempt_at' => null, 'payload' => [...($current->payload ?? []), 'action' => $action, 'requested_signature' => $signature]]);
                \App\Jobs\PublishToPlatform::dispatch($current->id)->afterCommit();
            });
        }
    }

    public function queueRemoval(Publication $publication): bool
    {
        try { $connector = $this->registry->get($publication->provider); } catch (UnsupportedCapability) { return false; }
        if (! $publication->external_id || in_array($publication->status, ['queued', 'processing'], true) || $publication->remote_status === 'deleted'
            || ! $connector instanceof \App\Contracts\ManagesPublications || ! in_array('delete', $connector->actions(), true)) return false;
        return DB::transaction(function () use ($publication) {
            $current = Publication::whereKey($publication->id)->lockForUpdate()->first();
            if (in_array($current->status, ['queued', 'processing'], true)) return false;
            $current->update(['status' => 'queued', 'error' => null, 'next_attempt_at' => null, 'payload' => [...($current->payload ?? []), 'action' => 'delete']]);
            \App\Jobs\PublishToPlatform::dispatch($current->id)->afterCommit();
            return true;
        });
    }

    public function records(): \Illuminate\Support\Collection
    {
        return SourceRecord::query()->whereIn('kind', ['video', 'short', 'post', 'live'])->where('status', 'ready')
            ->where(fn ($query) => $query->whereNull('metadata->archive_data')->orWhere('metadata->archive_data', false))
            ->where(fn ($query) => $query->whereNull('metadata->library_only')->orWhere('metadata->library_only', false))
            ->latest('id')->limit(100)->get();
    }
}
