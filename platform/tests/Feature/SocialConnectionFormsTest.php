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
            if (in_array($provider, ['youtube', 'x', 'facebook'], true)) {
                $values['oauth_client_id'] = 'unused-id';
                $values['oauth_client_secret'] = 'unused-secret';
            }
            if ($provider === 'telegram') {
                $values['external_id'] = '@channel';
                $values['api_key'] = 'test-token';
            }
            $response = $this->putJson('/desktop/settings', $values)->assertOk()->assertJsonPath('provider', $provider);
            if (in_array($provider, ['youtube', 'x', 'facebook'], true)) {
                $response->assertJsonPath('connection_status', 'configured')->assertJsonPath('oauth_configured', true);
                $this->assertFalse(app(ConnectionStore::class)->connected($provider));
            }
            $this->putJson('/desktop/settings', [...$values, 'webhook_secret' => 'unused-secret'])->assertUnprocessable()->assertJsonValidationErrors('webhook_secret');
            if (in_array($provider, ['youtube', 'x', 'facebook'], true)) {
                $oauthProvider = $provider === 'facebook' ? 'meta' : $provider;
                $stored = DB::table('settings')->where('key', 'secret.oauth_'.$oauthProvider.'_client_secret')->value('value');
                $this->assertIsString($stored);
                $this->assertStringNotContainsString('unused-secret', $stored);
                $this->get('/desktop')->assertDontSee('unused-secret');
            } else {
                $this->putJson('/desktop/settings', [...$values, 'oauth_client_id' => 'unused-id'])->assertUnprocessable()->assertJsonValidationErrors('oauth_client_id');
            }
        }
        foreach (['youtube', 'facebook', 'instagram', 'x', 'tiktok', 'linkedin'] as $provider) {
            $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => $provider, 'api_key' => 'unused-token'])->assertUnprocessable()->assertJsonValidationErrors('api_key');
        }
        Http::assertNothingSent();
    }

    public function test_oauth_connections_cannot_be_saved_until_required_app_credentials_exist(): void
    {
        foreach (['youtube', 'x', 'facebook'] as $provider) {
            $payload = ['section' => 'social', 'provider' => $provider];
            $required = $provider === 'x' ? ['oauth_client_id'] : ['oauth_client_id', 'oauth_client_secret'];
            $this->putJson('/desktop/settings', $payload)->assertUnprocessable()->assertJsonValidationErrors($required);
            if ($provider !== 'x') $this->putJson('/desktop/settings', [...$payload, 'oauth_client_id' => 'client-id'])->assertUnprocessable()->assertJsonValidationErrors('oauth_client_secret');
            $this->assertArrayNotHasKey($provider, app(Settings::class)->get('social_connections', []));
        }
        app(Settings::class)->update(['social_connections' => ['youtube' => ['provider' => 'youtube', 'configured_at' => now()->toIso8601String()]]]);
        $this->assertSame('not_configured', app(SocialConnections::class)->editor()['youtube']['status']);
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'instagram'])->assertOk();
        $this->assertArrayNotHasKey('instagram', app(Settings::class)->get('social_connections', []));
        Http::assertNothingSent();
    }

    public function test_empty_profile_only_forms_do_not_create_or_keep_empty_connection_rows(): void
    {
        foreach (['instagram', 'tiktok', 'linkedin'] as $provider) {
            $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => $provider])
                ->assertOk()->assertJsonPath('connection_saved', false);
            $this->assertArrayNotHasKey($provider, app(Settings::class)->get('social_connections', []));
        }

        app(Settings::class)->update(['social_connections' => ['tiktok' => ['provider' => 'tiktok', 'public_url' => 'https://tiktok.com/@example']]]);
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'tiktok', 'public_url' => null])
            ->assertOk()->assertJsonPath('connection_saved', false);
        $this->assertArrayNotHasKey('tiktok', app(Settings::class)->get('social_connections', []));
        Http::assertNothingSent();
    }

    public function test_channel_sending_requires_an_identifier_and_token_not_a_public_url(): void
    {
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'telegram'])->assertUnprocessable()->assertJsonValidationErrors(['external_id', 'api_key']);
        foreach (['facebook', 'instagram'] as $provider) $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => $provider, 'external_id' => '123', 'access_token' => 'token'])
            ->assertUnprocessable()->assertJsonValidationErrors(['external_id', 'access_token']);
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'telegram', 'external_id' => '@channel', 'api_key' => 'bot-token'])
            ->assertOk()->assertJsonPath('connection_saved', true)->assertJsonPath('connected', true)
            ->assertJsonPath('check_url', route('desktop.publishing.telegram.check'))
            ->assertJsonPath('disconnect_url', route('desktop.publishing.telegram.disconnect'));
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
        app(OAuthAppCredentials::class)->save('meta', ['client_id' => 'meta-id', 'client_secret' => 'meta-secret']);
        $settings->update(['social_connections' => ['youtube' => ['external_id' => 'channel-id', 'revoked_at' => now()->toIso8601String()], 'facebook' => ['external_id' => '123']]]);
        $store->saveCredentials('youtube', ['access_token' => 'old-access', 'refresh_token' => 'old-refresh']);
        $store->saveCredentials('facebook', ['access_token' => 'page-token', 'other' => 'preserved']);
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'youtube', 'public_url' => 'https://youtube.com/@example'])->assertOk();
        $this->assertSame('channel-id', $store->connection('youtube')['external_id']);
        $this->assertSame('old-refresh', $store->credentials('youtube')['refresh_token']);
        $this->assertFalse($store->connected('youtube'));
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'facebook', 'public_url' => 'https://facebook.com/page'])->assertOk();
        $this->assertSame('page-token', $store->credentials('facebook')['access_token']);
        $this->putJson('/desktop/settings', ['section' => 'social', 'provider' => 'facebook', 'access_token' => 'new-page-token'])->assertUnprocessable()->assertJsonValidationErrors('access_token');
        $this->assertSame('preserved', $store->credentials('facebook')['other']);
        $this->assertSame('page-token', $store->credentials('facebook')['access_token']);
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
        foreach (['youtube', 'x', 'facebook', 'instagram'] as $provider) {
            $this->assertNotEmpty($editor[$provider]['oauth_url']);
            $callbackProvider = in_array($provider, ['facebook', 'instagram'], true) ? 'meta' : $provider;
            $this->assertSame(route('desktop.publishing.'.$callbackProvider.'.callback'), $editor[$provider]['oauth_redirect_uri']);
            $this->assertFalse($editor[$provider]['oauth_configured']);
            $this->assertSame('not_configured', $editor[$provider]['status']);
        }
        foreach (['youtube', 'x', 'facebook', 'instagram', 'telegram'] as $provider) {
            $this->assertNotEmpty($editor[$provider]['check_url']);
            $this->assertNotEmpty($editor[$provider]['disconnect_url']);
        }
        foreach (['instagram', 'telegram', 'tiktok', 'linkedin'] as $provider) {
            $definition = $editor[$provider];
            $this->assertNotContains('webhook_secret', $definition['fields']);
            $this->assertNotContains('oauth_client_id', $definition['fields']);
        }
        foreach (['youtube', 'x', 'facebook'] as $provider) $this->assertContains('oauth_client_id', $editor[$provider]['fields']);
        $this->get('/desktop')->assertOk()->assertSee('data-social-definitions', false)->assertSee('name="bot_username"', false)
            ->assertSee('data-connection-oauth', false)->assertSee('data-connection-redirect-uri', false)
            ->assertSee(json_encode(route('desktop.publishing.youtube.callback'), JSON_UNESCAPED_UNICODE), false)
            ->assertSee(json_encode(route('desktop.publishing.x.callback'), JSON_UNESCAPED_UNICODE), false)
            ->assertSee(json_encode(route('desktop.publishing.meta.callback'), JSON_UNESCAPED_UNICODE), false)
            ->assertSee(__('social.status_not_configured'));
    }

    public function test_saved_social_cards_expose_only_applicable_native_actions(): void
    {
        app(OAuthAppCredentials::class)->save('youtube', ['client_id' => 'client-id', 'client_secret' => 'client-secret']);
        app(Settings::class)->update(['social_connections' => [
            'youtube' => ['provider' => 'youtube', 'configured_at' => now()->toIso8601String()],
            'telegram' => ['provider' => 'telegram', 'external_id' => '@channel', 'configured_at' => now()->toIso8601String()],
        ]]);
        app(ConnectionStore::class)->saveCredentials('telegram', ['api_key' => 'bot-token']);

        $this->get('/desktop')->assertOk()
            ->assertSee(route('desktop.publishing.youtube.connect'), false)
            ->assertSee(route('desktop.publishing.telegram.check'), false)
            ->assertSee(route('desktop.publishing.telegram.disconnect'), false)
            ->assertSee('data-social-action="check"', false)
            ->assertSee('data-social-action="disconnect"', false)
            ->assertSee(__('social.edit'))
            ->assertSee(__('social.connect_oauth'))
            ->assertSee(__('social.check_connection'))
            ->assertSee(__('social.disconnect'))
            ->assertDontSee('bot-token');
    }
}
