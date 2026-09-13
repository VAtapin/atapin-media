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
            if ($publication->exists && in_array($publication->status, ['processing', 'published'], true)) continue;
            $publication->fill(['status' => 'queued', 'error' => null, 'next_attempt_at' => null, 'payload' => $this->payload($record, $provider)]);
            $publication->save();
            \App\Jobs\PublishToPlatform::dispatch($publication->id)->afterCommit();
        }
    }

    public function execute(int $publicationId): void
    {
        $publication = Publication::with('record')->find($publicationId);
        if (! $publication || $publication->direction !== 'outbound' || in_array($publication->status, ['processing', 'published'], true)) return;
        $publication->update(['status' => 'processing', 'attempts' => $publication->attempts + 1, 'last_attempt_at' => now(), 'error' => null]);
        try {
            $result = $this->registry->get($publication->provider)->publish($publication->fresh('record'));
            $publication->update([
                'status' => 'published',
                'external_id' => $result['external_id'] ?? $publication->external_id,
                'external_url' => $result['external_url'] ?? $publication->external_url,
                'remote_status' => $result['remote_status'] ?? 'published',
                'payload' => [...($publication->payload ?? []), ...($result['payload'] ?? [])],
                'published_at' => now(),
                'next_attempt_at' => null,
            ]);
        } catch (UnsupportedCapability $error) {
            $publication->update(['status' => 'skipped', 'remote_status' => 'unsupported', 'error' => $error->getMessage(), 'next_attempt_at' => null]);
        } catch (\Throwable $error) {
            $publication->update(['status' => 'failed', 'error' => mb_substr($error->getMessage(), 0, 4000), 'next_attempt_at' => now()->addMinutes(min(60, max(1, $publication->attempts * 5)))]);
        }
    }

    public function retry(Publication $publication): void
    {
        $publication->update(['status' => 'queued', 'error' => null, 'next_attempt_at' => null]);
        \App\Jobs\PublishToPlatform::dispatch($publication->id)->afterCommit();
    }

    public function payload(SourceRecord $record, string $provider): array
    {
        return ['title' => $record->title, 'kind' => $record->kind, 'requested_at' => now()->toIso8601String(), 'provider' => $provider];
    }

    public function records(): \Illuminate\Support\Collection
    {
        return SourceRecord::query()->whereIn('kind', ['video', 'short', 'post', 'live'])->where('status', 'ready')
            ->where(fn ($query) => $query->whereNull('metadata->archive_data')->orWhere('metadata->archive_data', false))
            ->where(fn ($query) => $query->whereNull('metadata->library_only')->orWhere('metadata->library_only', false))
            ->latest('id')->limit(100)->get();
    }
}
