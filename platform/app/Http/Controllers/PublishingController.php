<?php

namespace App\Http\Controllers;

use App\Jobs\SyncYouTubeChannel;
use App\Models\{Publication, SourceRecord};
use App\Services\Publishing\{ConnectionStore, ConnectorRegistry, PublishingService, YouTubeClient};
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublishingController extends Controller
{
    public function index(PublishingService $publishing, ConnectionStore $connections, ConnectorRegistry $registry)
    {
        return response()->json([
            'records' => $publishing->records()->map(fn (SourceRecord $record) => [
                'id' => $record->id, 'title' => $record->title, 'kind' => $record->kind,
                'public_published' => (bool) ($record->metadata['public_published'] ?? false),
            ])->values(),
            'destinations' => [
                ['provider' => 'website', 'label' => 'Website', 'connected' => true, 'capabilities' => ['video' => true, 'short' => true, 'post' => true, 'live' => true]],
                ...collect($connections->publicConnections())->map(function (array $connection) use ($registry) {
                    $capabilities = [];
                    try { $capabilities = $registry->get($connection['provider'])->capabilities(); } catch (\Throwable) { }
                    return [...$connection, 'label' => ucfirst($connection['provider']), 'capabilities' => $capabilities];
                })->values()->all(),
            ],
            'publications' => Publication::with('record')->latest('updated_at')->limit(100)->get()->map(fn (Publication $item) => [
                'id' => $item->id, 'record_id' => $item->source_record_id, 'title' => $item->record?->title,
                'provider' => $item->provider, 'direction' => $item->direction, 'status' => $item->status,
                'external_id' => $item->external_id, 'external_url' => $item->external_url, 'error' => $item->error,
                'attempts' => $item->attempts, 'published_at' => $item->published_at?->toIso8601String(),
                'last_attempt_at' => $item->last_attempt_at?->toIso8601String(), 'next_attempt_at' => $item->next_attempt_at?->toIso8601String(),
            ])->values(),
            'youtube' => ['configured' => app(YouTubeClient::class)->configured(), 'channel' => $connections->connection('youtube')['public_url'] ?? null],
        ]);
    }

    public function publish(Request $request, PublishingService $publishing, ConnectionStore $connections)
    {
        $data = $request->validate(['record_id' => 'required|integer|exists:source_records,id', 'destinations' => 'required|array|min:1', 'destinations.*' => 'string|in:website,youtube,facebook,instagram,telegram,tiktok,linkedin,x']);
        $record = SourceRecord::findOrFail($data['record_id']);
        $this->assertPublishable($record);
        $destinations = array_values(array_unique($data['destinations']));
        foreach (array_diff($destinations, ['website']) as $provider) {
            abort_unless($connections->connected($provider) && ! ($connections->connection($provider)['revoked_at'] ?? false), 422, ucfirst($provider).' is not connected.');
        }
        $wasPublic = (bool) (($record->metadata ?? [])['public_published'] ?? false);
        DB::transaction(function () use ($record, $destinations) {
            $metadata = $record->metadata ?? [];
            $metadata['publishing_targets'] = $destinations;
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
        $publishing->retry($publication);
        return response()->json(['status' => 'queued', 'publication_id' => $publication->id]);
    }

    public function syncYouTube()
    {
        SyncYouTubeChannel::dispatch()->afterCommit();
        return response()->json(['status' => 'queued']);
    }

    public function youtubeConnect(Request $request, YouTubeClient $client)
    {
        abort_unless(config('publishing.youtube.client_id') && config('publishing.youtube.client_secret'), 503, 'YouTube OAuth is not configured.');
        $state = bin2hex(random_bytes(24));
        $request->session()->put('publishing.youtube.oauth_state', $state);
        return redirect()->away($client->authorizeUrl($state));
    }

    public function youtubeCallback(Request $request, YouTubeClient $client, ConnectionStore $connections, Settings $settings)
    {
        abort_unless(hash_equals((string) $request->session()->pull('publishing.youtube.oauth_state'), (string) $request->query('state')), 419);
        if ($request->query('error')) return redirect('/desktop')->with('status', 'YouTube OAuth was cancelled.');
        $credentials = $client->exchangeCode((string) $request->query('code'));
        $connections->saveCredentials('youtube', $credentials);
        $channel = $client->channel();
        $social = $settings->get('social_connections', []);
        $social['youtube'] = array_filter([
            'provider' => 'youtube', 'external_id' => $channel['id'] ?? null,
            'public_url' => isset($channel['snippet']['customUrl']) ? 'https://www.youtube.com/'.ltrim($channel['snippet']['customUrl'], '@') : null,
            'configured_at' => now()->toIso8601String(),
        ]);
        $settings->update(['social_connections' => $social]);
        return redirect('/desktop')->with('status', 'YouTube connected.');
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
}
