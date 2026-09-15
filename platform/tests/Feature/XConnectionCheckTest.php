<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\{Access, Settings};
use App\Services\Publishing\{ConnectionStore, OAuthAppCredentials, SocialConnections, XClient};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\{Http, Log};
use Tests\TestCase;

class XConnectionCheckTest extends TestCase
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

    public function test_check_refreshes_an_expiring_token_and_verifies_the_connected_profile(): void
    {
        $this->seedConnectedX([
            'access_token' => 'old-access-secret',
            'refresh_token' => 'refresh-secret',
            'expires_at' => now()->subMinute()->timestamp,
            'scope' => implode(' ', XClient::REQUIRED_SCOPES),
        ]);
        Http::fake([
            'https://api.x.com/2/oauth2/token' => Http::response([
                'access_token' => 'new-access-secret',
                'expires_in' => 7200,
                'scope' => implode(' ', XClient::REQUIRED_SCOPES),
            ]),
            'https://api.x.com/2/users/me*' => Http::response(['data' => [
                'id' => 'x-user-1', 'name' => 'Manna', 'username' => 'manna_media', 'protected' => false,
            ]]),
        ]);

        $response = $this->postJson(route('desktop.publishing.x.check'))->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('status_label', __('social.status_connected'))
            ->assertJsonPath('message', __('publishing.x_check_succeeded'))
            ->assertJsonPath('connection.x.id', 'x-user-1')
            ->assertJsonPath('connection.x.username', 'manna_media');

        foreach (['old-access-secret', 'new-access-secret', 'refresh-secret', 'x-client-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $credentials = app(ConnectionStore::class)->credentials('x');
        $this->assertSame('new-access-secret', $credentials['access_token']);
        $this->assertSame('refresh-secret', $credentials['refresh_token']);
        $this->assertNotEmpty(app(ConnectionStore::class)->connection('x')['checked_at']);
        $this->assertSame('connected', app(SocialConnections::class)->editor()['x']['status']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.x.com/2/oauth2/token'
            && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refresh-secret');
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.x.com/2/users/me')
            && $request->method() === 'GET');
    }

    public function test_check_reports_permission_failure_and_redacts_stored_secrets(): void
    {
        $this->seedConnectedX();
        $logs = $this->captureLogs();
        Http::fake(['https://api.x.com/2/users/me*' => Http::response([
            'detail' => 'Authorization: Bearer x-access-secret client_secret=x-client-secret',
        ], 403)]);

        $response = $this->postJson(route('desktop.publishing.x.check'))->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('status_label', __('social.status_error'))
            ->assertJsonPath('message', __('publishing.x_check_permissions'));

        $this->assertSame('error', app(SocialConnections::class)->editor()['x']['status']);
        $this->assertNotEmpty($logs);
        $this->assertSecretsAbsent($response->getContent().json_encode($logs, JSON_THROW_ON_ERROR));
    }

    public function test_check_reports_missing_oauth_scopes_as_a_permission_failure(): void
    {
        $this->seedConnectedX([
            'access_token' => 'x-access-secret',
            'refresh_token' => 'x-refresh-secret',
            'expires_at' => now()->addHour()->timestamp,
            'scope' => 'tweet.read users.read',
        ]);
        Http::fake(['https://api.x.com/2/users/me*' => Http::response(['data' => [
            'id' => 'x-user-1', 'username' => 'manna_media', 'protected' => false,
        ]])]);

        $this->postJson(route('desktop.publishing.x.check'))->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('publishing.x_check_permissions'));
        $this->assertSame('error', app(SocialConnections::class)->editor()['x']['status']);
    }

    public function test_check_reports_expired_and_generic_provider_failures_without_leaking_secrets(): void
    {
        $this->seedConnectedX();
        $logs = $this->captureLogs();
        Http::fake(['https://api.x.com/2/users/me*' => Http::sequence()
            ->push(['detail' => 'access_token=x-access-secret'], 401)
            ->push(['detail' => 'refresh_token=x-refresh-secret'], 503)]);

        $expired = $this->postJson(route('desktop.publishing.x.check'))->assertUnprocessable()
            ->assertJsonPath('status', 'expired')
            ->assertJsonPath('message', __('publishing.x_token_expired'));
        $this->assertNotEmpty(app(ConnectionStore::class)->connection('x')['revoked_at']);
        $this->assertNotEmpty($logs);
        $this->assertSecretsAbsent($expired->getContent().json_encode($logs, JSON_THROW_ON_ERROR));

        $this->seedConnectedX();
        $failed = $this->postJson(route('desktop.publishing.x.check'))->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', __('publishing.x_check_failed'));
        $this->assertSame('error', app(SocialConnections::class)->editor()['x']['status']);
        $this->assertSecretsAbsent($failed->getContent().json_encode($logs, JSON_THROW_ON_ERROR));
    }

    public function test_native_card_exposes_the_throttled_check_action_without_secrets(): void
    {
        $this->seedConnectedX();

        $this->get('/desktop?open=settings')->assertOk()
            ->assertSee('data-social-action="check"', false)
            ->assertSee(route('desktop.publishing.x.check'), false)
            ->assertDontSee('x-access-secret')
            ->assertDontSee('x-refresh-secret')
            ->assertDontSee('x-client-secret');

        $route = app('router')->getRoutes()->getByName('desktop.publishing.x.check');
        $this->assertNotNull($route);
        $this->assertContains('can:integrations.manage', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }

    public function test_check_requires_integration_management_permission(): void
    {
        $editor = User::factory()->create();
        $editor->roles()->attach(Role::where('name', 'Editor')->firstOrFail());
        $this->actingAs($editor);

        $this->postJson(route('desktop.publishing.x.check'))->assertForbidden();
        Http::assertNothingSent();
    }

    private function seedConnectedX(array $credentials = []): void
    {
        app(OAuthAppCredentials::class)->save('x', ['client_id' => 'x-client-id', 'client_secret' => 'x-client-secret']);
        app(Settings::class)->update(['social_connections' => ['x' => [
            'provider' => 'x',
            'external_id' => 'x-user-1',
            'public_url' => 'https://x.com/manna_media',
            'configured_at' => now()->toIso8601String(),
        ]]]);
        app(ConnectionStore::class)->saveCredentials('x', $credentials ?: [
            'access_token' => 'x-access-secret',
            'refresh_token' => 'x-refresh-secret',
            'expires_at' => now()->addHour()->timestamp,
            'scope' => implode(' ', XClient::REQUIRED_SCOPES),
        ], true);
    }

    private function captureLogs(): \ArrayObject
    {
        $logs = new \ArrayObject;
        Log::listen(function (MessageLogged $event) use (&$logs) { $logs[] = [$event->message, $event->context]; });
        return $logs;
    }

    private function assertSecretsAbsent(string $value): void
    {
        foreach (['x-access-secret', 'x-refresh-secret', 'x-client-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $value);
        }
    }
}
