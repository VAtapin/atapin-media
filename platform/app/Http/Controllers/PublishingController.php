<?php

namespace App\Http\Controllers;

use App\Jobs\SyncYouTubeChannel;
use App\Models\{Publication, SourceRecord};
use App\Services\Publishing\{ConnectionStore, ConnectorRegistry, PublishingService, YouTubeClient};
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Log};

class PublishingController extends Controller
{
    public function index(Request $request, PublishingService $publishing, ConnectionStore $connections, ConnectorRegistry $registry)
    {
        $filters = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'provider' => 'nullable|string|regex:/^[a-z0-9_]{1,32}$/',
            'status' => 'nullable|in:queued,processing,published,hidden,private,public,failed,skipped,deleted',
            'search' => 'nullable|string|max:120',
        ]);
        $query = Publication::with('record')->where('direction', 'outbound')->where('provider', '!=', 'website');
        if (! empty($filters['provider'])) $query->where('provider', $filters['provider']);
        if (! empty($filters['status'])) $query->where(function ($status) use ($filters) {
            $status->where('status', $filters['status'])->orWhere('remote_status', $filters['status']);
        });
        if (! empty($filters['search'])) $query->whereHas('record', fn ($record) => $record->where('title', 'like', '%'.$filters['search'].'%'));
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : null;
            $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : null;
            $query->where(function ($date) use ($from, $to) {
                $date->where(function ($published) use ($from, $to) {
                    $published->whereNotNull('published_at')->when($from, fn ($q) => $q->where('published_at', '>=', $from))->when($to, fn ($q) => $q->where('published_at', '<=', $to));
                })->orWhere(function ($updated) use ($from, $to) {
                    $updated->whereNull('published_at')->when($from, fn ($q) => $q->where('updated_at', '>=', $from))->when($to, fn ($q) => $q->where('updated_at', '<=', $to));
                });
            });
        }
        $publications = $query->latest('updated_at')->limit(250)->get();
        $providerOptions = $publications->pluck('provider')->merge(collect($connections->publicConnections())->pluck('provider'))->filter()->unique()->sort()->values();
        return response()->json([
            'records' => $publishing->records()->map(fn (SourceRecord $record) => [
                'id' => $record->id, 'title' => $record->title, 'kind' => $record->publishingKind(),
                'public_published' => (bool) ($record->metadata['public_published'] ?? false),
                'publishing_targets' => $record->metadata['publishing_targets'] ?? null,
                'remove_external_on_unpublish' => (bool) ($record->metadata['remove_external_on_unpublish'] ?? false),
            ])->values(),
            'destinations' => [
                ['provider' => 'website', 'label' => 'Website', 'connected' => true, 'capabilities' => ['video' => true, 'short' => true, 'post' => true, 'live' => true]],
                ...collect($connections->publicConnections())->map(function (array $connection) use ($registry) {
                    $capabilities = [];
                    try { $capabilities = $registry->get($connection['provider'])->capabilities(); } catch (\Throwable) { }
                    return [...$connection, 'label' => $connection['label'] ?? ucfirst($connection['provider']), 'capabilities' => $capabilities];
                })->values()->all(),
            ],
            'publications' => $publications->map(function (Publication $item) use ($registry) {
                $actions = [];
                try { $connector = $registry->get($item->provider); if ($connector instanceof \App\Contracts\ManagesPublications) $actions = $connector->actions(); } catch (\Throwable) { }
                return [
                'id' => $item->id, 'record_id' => $item->source_record_id, 'title' => $item->record?->title,
                'provider' => $item->provider, 'direction' => $item->direction, 'status' => $item->status,
                'remote_status' => $item->remote_status,
                'external_id' => $item->external_id, 'external_url' => $item->external_url, 'error' => $item->error,
                'origin' => ($item->payload['origin'] ?? null) === 'youtube_sync' ? 'youtube_sync' : 'outbound',
                'attempts' => $item->attempts, 'published_at' => $item->published_at?->toIso8601String(),
                'last_attempt_at' => $item->last_attempt_at?->toIso8601String(), 'next_attempt_at' => $item->next_attempt_at?->toIso8601String(),
                'can_remove' => $item->external_id && in_array('delete', $actions, true) && ! in_array($item->status, ['queued', 'processing'], true) && $item->remote_status !== 'deleted',
                'can_activate' => $item->external_id && in_array('update', $actions, true) && ! in_array($item->status, ['queued', 'processing'], true) && in_array($item->remote_status, ['hidden', 'private'], true),
                'can_deactivate' => $item->external_id && in_array('hide', $actions, true) && ! in_array($item->status, ['queued', 'processing'], true) && ! in_array($item->remote_status, ['hidden', 'private', 'deleted'], true),
            ]; })->values(),
            'providers' => $providerOptions,
            'filters' => ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'provider' => $filters['provider'] ?? null, 'status' => $filters['status'] ?? null, 'search' => $filters['search'] ?? null],
            'youtube' => ['configured' => app(YouTubeClient::class)->configured(), 'channel' => $connections->connection('youtube')['public_url'] ?? null],
        ]);
    }

    public function visibility(Request $request, Publication $publication, PublishingService $publishing)
    {
        $data = $request->validate(['active' => 'required|boolean']);
        abort_unless($publishing->queueVisibility($publication, $data['active']), 409, __('publishing.visibility_unavailable'));
        return response()->json(['status' => 'queued', 'publication_id' => $publication->id]);
    }

    public function publish(Request $request, PublishingService $publishing, ConnectionStore $connections)
    {
        $data = $request->validate(['record_id' => 'required|integer|exists:source_records,id', 'destinations' => 'required|array|min:1', 'destinations.*' => ['string', 'regex:/^(website|youtube|facebook|instagram|telegram|x|linkedin|rtmp_[a-z0-9_]{1,27})$/']]);
        $record = SourceRecord::findOrFail($data['record_id']);
        $request->validate(['remove_external_on_unpublish' => 'nullable|boolean']);
        $this->assertPublishable($record);
        $destinations = array_values(array_unique($data['destinations']));
        foreach (array_diff($destinations, ['website']) as $provider) {
            abort_unless($connections->connected($provider) && ! ($connections->connection($provider)['revoked_at'] ?? false), 422, ucfirst($provider).' is not connected.');
        }
        $wasPublic = (bool) (($record->metadata ?? [])['public_published'] ?? false);
        DB::transaction(function () use ($record, $destinations, $request) {
            $metadata = $record->metadata ?? [];
            $metadata['publishing_targets'] = $destinations;
            if ($request->has('remove_external_on_unpublish')) $metadata['remove_external_on_unpublish'] = $request->boolean('remove_external_on_unpublish');
            if (in_array('website', $destinations, true)) {
                $metadata['public_published'] = true;
                $metadata['public_published_at'] ??= now()->toIso8601String();
                $metadata['public_section'] ??= match ($record->kind) { 'post' => 'beitraege', 'live' => 'live', default => 'videos' };
                unset($metadata['external_sync_pending_review']);
            }
            $record->update(['metadata' => $metadata, 'status' => 'ready']);
        });
        if (! in_array('website', $destinations, true) || $wasPublic) $publishing->queueForRecord($record->fresh(), $destinations);
        return response()->json(['status' => 'queued', 'record_id' => $record->id]);
    }

    public function retry(Publication $publication, PublishingService $publishing)
    {
        abort_unless($publishing->retry($publication), 409, __('publishing.retry_unavailable'));
        return response()->json(['status' => 'queued', 'publication_id' => $publication->id]);
    }

    public function removePublication(Request $request, Publication $publication, PublishingService $publishing)
    {
        $request->validate(['confirm' => 'required|accepted']);
        abort_unless($publishing->queueRemoval($publication), 409);
        return response()->json(['status' => 'queued']);
    }

    public function saveLiveOutput(Request $request, ConnectionStore $connections)
    {
        $data = $request->validate(['id' => ['required', 'regex:/^rtmp_[a-z0-9_]{1,27}$/'], 'label' => 'required|string|max:100', 'url' => ['required', 'string', 'max:2048', 'regex:~^rtmps?://[^\s]+$~']]);
        $parts = parse_url($data['url']);
        abort_unless($parts && ! empty($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['fragment']), 422);
        $connections->saveLiveOutput($data['id'], $data['label'], $data['url']);
        return response()->json(['status' => 'saved']);
    }

    public function removeLiveOutput(string $output, ConnectionStore $connections)
    {
        abort_unless(preg_match('/^rtmp_[a-z0-9_]{1,27}$/', $output), 404);
        $connections->removeLiveOutput($output);
        return response()->json(['status' => 'removed']);
    }

    public function disconnectX(ConnectionStore $connections)
    {
        $connections->forgetCredentials('x');
        return response()->json(['status' => 'disconnected']);
    }

    public function syncYouTube()
    {
        SyncYouTubeChannel::dispatch()->afterCommit();
        return response()->json(['status' => 'queued']);
    }

    public function youtubeConnect(Request $request, YouTubeClient $client)
    {
        if (! $client->oauthConfigured()) {
            return $this->youtubeOAuthReturn(__('publishing.youtube_oauth_not_configured'), true);
        }
        $state = bin2hex(random_bytes(24));
        $request->session()->put('publishing.youtube.oauth_state', $state);
        return redirect()->away($client->authorizeUrl($state));
    }

    public function youtubeCallback(Request $request, YouTubeClient $client, ConnectionStore $connections, Settings $settings)
    {
        $expected = $request->session()->pull('publishing.youtube.oauth_state');
        $state = $request->query('state');
        if (! is_string($expected) || $expected === '' || ! is_string($state) || $state === '' || ! hash_equals($expected, $state)) {
            Log::warning('YouTube OAuth callback rejected because its state was missing or invalid.', ['user_id' => $request->user()?->id]);
            return $this->youtubeOAuthReturn(__('publishing.oauth_state_invalid'), true);
        }
        if ($request->query('error')) return $this->youtubeOAuthReturn(__('publishing.oauth_cancelled'));
        $code = $request->query('code');
        if (! is_string($code) || $code === '') return $this->youtubeOAuthReturn(__('publishing.youtube_connection_failed'), true);

        try {
            $credentials = $client->exchangeCode($code);
            $channel = $client->channel($credentials);
            if (! is_string($channel['id'] ?? null) || $channel['id'] === '') throw new \RuntimeException('YouTube returned no accessible channel.');
            $social = $settings->get('social_connections', []);
            $sameChannel = ($social['youtube']['external_id'] ?? null) === $channel['id'];
            $social['youtube'] = array_filter([
                'provider' => 'youtube', 'external_id' => $channel['id'],
                'public_url' => isset($channel['snippet']['customUrl']) ? 'https://www.youtube.com/'.$channel['snippet']['customUrl'] : 'https://www.youtube.com/channel/'.$channel['id'],
                'configured_at' => now()->toIso8601String(),
            ]);
            DB::transaction(function () use ($connections, $credentials, $sameChannel, $settings, $social) {
                $connections->saveCredentials('youtube', $credentials, ! $sameChannel);
                $settings->update(['social_connections' => $social]);
            });
        } catch (\Throwable $error) {
            Log::warning('YouTube OAuth connection failed.', [
                'user_id' => $request->user()?->id,
                'error_type' => $error::class,
                'error' => $connections->safeError($error),
            ]);
            return $this->youtubeOAuthReturn(__('publishing.youtube_connection_failed'), true);
        }

        return $this->youtubeOAuthReturn(__('publishing.youtube_connected'));
    }

    public function youtubeDisconnect(ConnectionStore $connections)
    {
        $connections->forgetCredentials('youtube');
        return response()->json(['status' => 'revoked']);
    }

    private function assertPublishable(SourceRecord $record): void
    {
        abort_unless(in_array($record->kind, ['video', 'short', 'post', 'live'], true), 422, 'This content type cannot be published.');
        abort_unless($record->status === 'ready', 422, 'Only ready content can be published.');
        $metadata = $record->metadata ?? [];
        abort_unless(! ($metadata['archive_data'] ?? false) && ! ($metadata['library_only'] ?? false), 422, 'Private archive content cannot be published.');
    }

    private function youtubeOAuthReturn(string $message, bool $error = false)
    {
        return redirect('/desktop?open=settings')->with('saved_section', 'social')->with($error ? 'desktop_error' : 'status', $message);
    }
}
