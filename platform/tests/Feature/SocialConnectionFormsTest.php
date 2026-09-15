<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\{Access, Settings};
use App\Services\Publishing\{ConnectionStore, OAuthAppCredentials, SocialConnections};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Queue};
use Tests\TestCase;

class SocialConnectionFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        app(Access::class)->seed();
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($owner);
    }

    public function test_each_provider_accepts_only_its_actual_fields(): void
    {
        foreach (SocialConnections::definitions() as $provider => $definition) {
            $values = ['section' => 'social', 'provider' => $provider, 'public_url' => 'https://example.org/profile'];
            if (in_array($provider, ['youtube', 'x'], true)) {
                $values['oauth_client_id'] = 'unused-id';
                $values['oauth_client_secret'] = 'unused-secret';
            }
            if (in_array($provider, ['facebook', 'instagram', 'telegram'])) {
                $values['external_id'] = $provider === 'telegram' ? '@channel' : '123';
                $values[$provider === 'telegram' ? 'api_key' : 'access_token'] = 'test-token';
            }
            $response = $this->putJson('/desktop/settings', $values)->assertOk()->assertJsonPath('provider', $provider);
            if (in_array($provider, ['youtube', 'x'], true)) {
                $response->assertJsonPath('connection_status', 'configured')->assertJsonPath('oauth_configured', true);
                $this->assertFalse(app(ConnectionStore::class)->connected($provider));
            }
            $this->putJson('/desktop/settings', [...$values, 'webhook_secret' => 'unused-secret'])->assertUnprocessable()->assertJsonValidationErrors('webhook_secret');
            if (in_array($provider, ['youtube', 'x'], true)) {
                $stored = DB::table('settings')->where('key', 'secret.oauth_'.$provider.'_client_secret')->value('value');
                $this->assertIsString($stored);
                $this->assertStringNotContainsString('unused-secret', $stored);
                $this->get('/desktop')->assertDontSee('unused-secret');
            } else {
                $this->putJson('/desktop/settings', [...$values, 'oauth_client_id' => 'unused-id'])->assertUnprocessable()->assertJsonValidationErrors('oauth_client_id');
            }
        }
        foreach (['youtube', 'x', 'tiktok', 'linkedin'] as $provider) {
            $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => $provider, 'api_key' => 'unused-token'])->assertUnprocessable()->assertJsonValidationErrors('api_key');
        }
        Http::assertNothingSent();
    }

    public function test_oauth_connections_cannot_be_saved_until_required_app_credentials_exist(): void
    {
        foreach (['youtube', 'x'] as $provider) {
            $payload = ['section' => 'social', 'provider' => $provider];
            $required = $provider === 'youtube' ? ['oauth_client_id', 'oauth_client_secret'] : ['oauth_client_id'];
            $this->putJson('/desktop/settings', $payload)->assertUnprocessable()->assertJsonValidationErrors($required);
            if ($provider === 'youtube') $this->putJson('/desktop/settings', [...$payload, 'oauth_client_id' => 'client-id'])->assertUnprocessable()->assertJsonValidationErrors('oauth_client_secret');
            $this->assertArrayNotHasKey($provider, app(Settings::class)->get('social_connections', []));
        }
        app(Settings::class)->update(['social_connections' => ['youtube' => ['provider' => 'youtube', 'configured_at' => now()->toIso8601String()]]]);
        $this->assertSame('not_configured', app(SocialConnections::class)->editor()['youtube']['status']);
        Http::assertNothingSent();
    }

    public function test_channel_sending_requires_an_identifier_and_token_not_a_public_url(): void
    {
        foreach (['facebook', 'instagram', 'telegram'] as $provider) {
            $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => $provider])->assertUnprocessable()->assertJsonValidationErrors(['external_id', $provider === 'telegram' ? 'api_key' : 'access_token']);
        }
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'telegram', 'external_id' => '@channel', 'api_key' => 'bot-token'])->assertOk();
        $this->assertTrue(app(ConnectionStore::class)->connected('telegram'));
        $this->assertDatabaseMissing('settings', ['key' => 'secret.social_telegram', 'value' => json_encode('bot-token')]);
        $this->get('/desktop')->assertOk()->assertDontSee('bot-token');
        Http::assertNothingSent();
    }

    public function test_partial_saves_preserve_credentials_and_do_not_reconnect_revoked_accounts(): void
    {
        $settings = app(Settings::class);
        $store = app(ConnectionStore::class);
        app(OAuthAppCredentials::class)->save('youtube', ['client_id' => 'client-id', 'client_secret' => 'client-secret']);
        $settings->update(['social_connections' => ['youtube' => ['external_id' => 'channel-id', 'revoked_at' => now()->toIso8601String()], 'facebook' => ['external_id' => '123']]]);
        $store->saveCredentials('youtube', ['access_token' => 'old-access', 'refresh_token' => 'old-refresh']);
        $store->saveCredentials('facebook', ['access_token' => 'page-token', 'other' => 'preserved']);
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'youtube', 'public_url' => 'https://youtube.com/@example'])->assertOk();
        $this->assertSame('channel-id', $store->connection('youtube')['external_id']);
        $this->assertSame('old-refresh', $store->credentials('youtube')['refresh_token']);
        $this->assertFalse($store->connected('youtube'));
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'facebook', 'access_token' => ''])->assertOk();
        $this->assertSame('page-token', $store->credentials('facebook')['access_token']);
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'facebook', 'access_token' => 'new-page-token'])->assertOk();
        $this->assertSame('preserved', $store->credentials('facebook')['other']);
        $this->assertSame('new-page-token', $store->credentials('facebook')['access_token']);
    }

    public function test_mini_app_only_and_profile_links_are_not_publishing_connections(): void
    {
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'telegram', 'mini_app_enabled' => true])->assertUnprocessable()->assertJsonValidationErrors('bot_username');
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'telegram', 'mini_app_enabled' => true, 'bot_username' => 'ExampleBot'])->assertOk();
        $store = app(ConnectionStore::class);
        $this->assertFalse($store->connected('telegram'));
        foreach (['tiktok', 'linkedin'] as $provider) {
            app(Settings::class)->update(['social_connections' => [...app(Settings::class)->get('social_connections'), $provider => ['external_id' => 'old-id']]]);
            $store->saveCredentials($provider, ['access_token' => 'old-token']);
            $this->assertFalse($store->connected($provider));
        }
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => ['telegram']])->assertUnprocessable()->assertJsonValidationErrors('provider');
        Http::assertNothingSent();
    }

    public function test_editor_has_oauth_actions_and_no_unrelated_social_fields(): void
    {
        $editor = app(SocialConnections::class)->editor();
        foreach (['youtube', 'x'] as $provider) {
            $this->assertNotEmpty($editor[$provider]['oauth_url']);
            $this->assertSame(route('desktop.publishing.'.$provider.'.callback'), $editor[$provider]['oauth_redirect_uri']);
            $this->assertFalse($editor[$provider]['oauth_configured']);
            $this->assertSame('not_configured', $editor[$provider]['status']);
        }
        foreach (['facebook', 'instagram', 'telegram', 'tiktok', 'linkedin'] as $provider) {
            $definition = $editor[$provider];
            $this->assertNotContains('webhook_secret', $definition['fields']);
            $this->assertNotContains('oauth_client_id', $definition['fields']);
        }
        foreach (['youtube', 'x'] as $provider) $this->assertContains('oauth_client_id', $editor[$provider]['fields']);
        $this->get('/desktop')->assertOk()->assertSee('data-social-definitions', false)->assertSee('name="bot_username"', false)
            ->assertSee('data-connection-oauth', false)->assertSee('data-connection-redirect-uri', false)
            ->assertSee(__('social.status_not_configured'));
    }
}
