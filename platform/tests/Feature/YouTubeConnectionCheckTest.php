<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\{Access, Settings};
use App\Services\Publishing\{ConnectionStore, OAuthAppCredentials, SocialConnections};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\{Http, Log};
use Tests\TestCase;

class YouTubeConnectionCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        app(Access::class)->seed();
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($owner);
    }

    public function test_check_refreshes_token_and_verifies_scope_audience_and_channel_identity(): void
    {
        $this->connectYouTube([
            'access_token' => 'old-access-secret',
            'refresh_token' => 'refresh-secret',
            'expires_at' => now()->subMinute()->timestamp,
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-secret', 'expires_in' => 3600,
            ]),
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'youtube-client-id',
                'scope' => 'https://www.googleapis.com/auth/youtube',
                'expires_in' => 3599,
            ]),
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['id' => 'channel-1', 'snippet' => ['title' => 'Verified channel', 'customUrl' => '@verified']]],
            ]),
        ]);

        $response = $this->postJson(route('desktop.publishing.youtube.check'))
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('connection.id', 'channel-1')
            ->assertJsonPath('connection.name', 'Verified channel')
            ->assertJsonPath('connection.publishing_scope', true);

        $credentials = app(ConnectionStore::class)->credentials('youtube');
        $this->assertSame('new-access-secret', $credentials['access_token']);
        $this->assertSame('refresh-secret', $credentials['refresh_token']);
        $connection = app(ConnectionStore::class)->connection('youtube');
        $this->assertSame('Verified channel', $connection['display_name']);
        $this->assertSame('https://www.youtube.com/@verified', $connection['public_url']);
        $this->assertNotEmpty($connection['checked_at']);
        $this->assertSame('connected', app(SocialConnections::class)->editor()['youtube']['status']);
        $this->assertStringNotContainsString('new-access-secret', $response->getContent());
        $this->assertStringNotContainsString('refresh-secret', $response->getContent());
        $this->get('/desktop')->assertOk()->assertDontSee('new-access-secret')->assertDontSee('refresh-secret')
            ->assertSee('data-social-action="check"', false)
            ->assertSee(route('desktop.publishing.youtube.check'), false);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['refresh_token'] === 'refresh-secret');
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://oauth2.googleapis.com/tokeninfo?')
            && $request['access_token'] === 'new-access-secret');
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://www.googleapis.com/youtube/v3/channels?')
            && $request->hasHeader('Authorization', 'Bearer new-access-secret'));
    }

    public function test_check_reports_missing_publish_scope_as_permission_problem(): void
    {
        $this->connectYouTube(['access_token' => 'access-secret', 'refresh_token' => 'refresh-secret']);
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'youtube-client-id',
                'scope' => 'https://www.googleapis.com/auth/youtube.readonly',
            ]),
        ]);

        $response = $this->postJson(route('desktop.publishing.youtube.check'))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'permission')
            ->assertJsonPath('status_label', __('social.status_permission'))
            ->assertJsonPath('message', __('publishing.youtube_check_permissions'));

        $this->assertSame('permission', app(SocialConnections::class)->editor()['youtube']['status']);
        $this->assertStringNotContainsString('access-secret', $response->getContent());
        Http::assertSentCount(1);
    }

    public function test_revoked_refresh_token_is_reported_as_expired_without_deleting_credentials(): void
    {
        $this->connectYouTube([
            'access_token' => 'expired-access-secret',
            'refresh_token' => 'revoked-refresh-secret',
            'expires_at' => now()->subMinute()->timestamp,
        ]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->postJson(route('desktop.publishing.youtube.check'))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('message', __('publishing.youtube_token_expired'));

        $store = app(ConnectionStore::class);
        $this->assertNotEmpty($store->connection('youtube')['revoked_at']);
        $this->assertSame('revoked-refresh-secret', $store->credentials('youtube')['refresh_token']);
        $this->assertSame('expired', app(SocialConnections::class)->editor()['youtube']['status']);
        $this->assertStringNotContainsString('revoked-refresh-secret', $response->getContent());
        Http::assertSentCount(1);
    }

    public function test_check_rejects_a_different_authorized_channel(): void
    {
        $this->connectYouTube(['access_token' => 'access-secret', 'refresh_token' => 'refresh-secret']);
        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'youtube-client-id',
                'scope' => 'https://www.googleapis.com/auth/youtube',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [['id' => 'another-channel', 'snippet' => ['title' => 'Wrong channel']]],
            ]),
        ]);

        $this->postJson(route('desktop.publishing.youtube.check'))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'permission')
            ->assertJsonPath('message', __('publishing.youtube_check_channel_identity'));

        $this->assertSame('permission', app(SocialConnections::class)->editor()['youtube']['status']);
    }

    public function test_provider_failures_are_logged_without_oauth_or_token_secrets(): void
    {
        $this->connectYouTube(['access_token' => 'access-secret-value', 'refresh_token' => 'refresh-secret-value']);
        $logs = [];
        Log::listen(function (MessageLogged $event) use (&$logs) { $logs[] = [$event->message, $event->context]; });
        Http::fake(function () {
            throw new \RuntimeException('access_token=access-secret-value client_secret=youtube-client-secret refresh_token=refresh-secret-value');
        });

        $response = $this->postJson(route('desktop.publishing.youtube.check'))
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('publishing.youtube_check_failed'));

        $encodedLogs = json_encode($logs, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($logs);
        $this->assertStringNotContainsString('access-secret-value', $encodedLogs);
        $this->assertStringNotContainsString('refresh-secret-value', $encodedLogs);
        $this->assertStringNotContainsString('youtube-client-secret', $encodedLogs);
        $this->assertStringNotContainsString('access-secret-value', $response->getContent());
    }

    public function test_manual_disconnect_keeps_oauth_configuration_but_removes_account_and_tokens(): void
    {
        $this->connectYouTube(['access_token' => 'access-secret', 'refresh_token' => 'refresh-secret']);

        $this->postJson(route('desktop.publishing.youtube.disconnect'))
            ->assertOk()
            ->assertJsonPath('status', 'configured')
            ->assertJsonPath('status_label', __('social.status_configured'))
            ->assertJsonPath('message', __('publishing.youtube_disconnected'));

        $store = app(ConnectionStore::class);
        $this->assertSame([], $store->credentials('youtube'));
        $this->assertNull($store->connection('youtube')['external_id']);
        $this->assertFalse($store->connected('youtube'));
        $this->assertSame('configured', app(SocialConnections::class)->editor()['youtube']['status']);
        Http::assertNothingSent();
    }

    public function test_check_requires_integration_permission_and_is_throttled(): void
    {
        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized)->postJson(route('desktop.publishing.youtube.check'))->assertForbidden();
        Http::assertNothingSent();

        $route = app('router')->getRoutes()->getByName('desktop.publishing.youtube.check');
        $this->assertNotNull($route);
        $this->assertContains('can:integrations.manage', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }

    private function connectYouTube(array $credentials): void
    {
        app(OAuthAppCredentials::class)->save('youtube', [
            'client_id' => 'youtube-client-id',
            'client_secret' => 'youtube-client-secret',
        ]);
        app(Settings::class)->update(['social_connections' => [
            'youtube' => [
                'provider' => 'youtube',
                'external_id' => 'channel-1',
                'public_url' => 'https://www.youtube.com/channel/channel-1',
                'configured_at' => now()->toIso8601String(),
            ],
        ]]);
        app(ConnectionStore::class)->saveCredentials('youtube', $credentials);
    }
}
