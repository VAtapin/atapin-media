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
        if ((($record->metadata ?? [])['public_published'] ?? false) && in_array('website', $providers, true)) {
            Publication::updateOrCreate(
                ['source_record_id' => $record->id, 'provider' => 'website', 'direction' => 'outbound'],
                ['status' => 'published', 'published_at' => now(), 'remote_status' => 'published', 'error' => null]
            );
        }
        if (! in_array($record->kind, ['video', 'short', 'post', 'live'], true)) return;
        foreach (array_diff($providers, ['website']) as $provider) {
            $publication = Publication::firstOrNew(['source_record_id' => $record->id, 'provider' => $provider, 'direction' => 'outbound']);
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
            if (! $this->connections->connected($publication->provider)) throw new UnsupportedCapability('The destination is disconnected.');
            $result = $this->registry->get($publication->provider)->publish($publication->fresh('record'));
            $publication->refresh();
            $publication->update([
                'status' => 'published',
                'external_id' => $result['external_id'] ?? $publication->external_id,
                'external_url' => $result['external_url'] ?? $publication->external_url,
                'remote_status' => $result['remote_status'] ?? 'published',
                'payload' => [...($publication->payload ?? []), ...($result['payload'] ?? [])],
                'published_at' => $publication->published_at ?? now(),
                'next_attempt_at' => null,
            ]);
            $record = $publication->record?->fresh();
            if ($record?->publishingKind() === 'live' && ($record->metadata['live_status'] ?? null) === 'ended') {
                $this->queueLiveCompletion($record);
            }
        } catch (PublicationPending) {
            $publication->update(['status' => 'queued', 'remote_status' => 'processing', 'next_attempt_at' => now()->addSeconds(30)]);
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
        $publication->update(['status' => 'failed', 'error' => $this->connections->safeError($error),
            'next_attempt_at' => $publication->attempts < 5 ? now()->addMinutes(min(60, max(1, $publication->attempts * 5))) : null]);
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
        return ['title' => $record->title, 'kind' => $record->publishingKind(), 'requested_at' => now()->toIso8601String(), 'provider' => $provider];
    }

    public function records(): \Illuminate\Support\Collection
    {
        return SourceRecord::query()->whereIn('kind', ['video', 'short', 'post', 'live'])->where('status', 'ready')
            ->where(fn ($query) => $query->whereNull('metadata->archive_data')->orWhere('metadata->archive_data', false))
            ->where(fn ($query) => $query->whereNull('metadata->library_only')->orWhere('metadata->library_only', false))
            ->latest('id')->limit(100)->get();
    }
}
