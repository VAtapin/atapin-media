<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\{Access, Settings};
use App\Services\Publishing\{ConnectionStore, MetaClient, OAuthAppCredentials, SocialConnections};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\{DB, Http, Log};
use Tests\TestCase;

class MetaOAuthTest extends TestCase
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

    public function test_meta_oauth_requires_app_credentials_and_uses_the_exact_shared_callback(): void
    {
        $this->get(route('desktop.publishing.meta.connect'))->assertRedirect('/desktop?open=settings')
            ->assertSessionHas('saved_section', 'social')
            ->assertSessionHas('desktop_error', __('publishing.meta_oauth_not_configured'));

        $this->putJson(route('settings'), [
            'section' => 'social', 'provider' => 'facebook',
            'oauth_client_id' => 'meta-app-id', 'oauth_client_secret' => 'meta-app-secret',
        ])->assertOk()->assertJsonPath('connection_status', 'configured')->assertJsonPath('oauth_configured', true);

        $editor = app(SocialConnections::class)->editor();
        $this->assertSame(route('desktop.publishing.meta.callback'), $editor['facebook']['oauth_redirect_uri']);
        $this->assertSame($editor['facebook']['oauth_redirect_uri'], $editor['instagram']['oauth_redirect_uri']);
        $this->assertTrue($editor['instagram']['oauth_configured']);

        $response = $this->get(route('desktop.publishing.meta.connect'))->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('meta-app-id', $query['client_id']);
        $this->assertSame(route('desktop.publishing.meta.callback'), $query['redirect_uri']);
        $this->assertSame(MetaClient::REQUIRED_SCOPES, explode(',', $query['scope']));
        $this->assertNotEmpty($query['state']);
        $response->assertSessionHas('publishing.meta.oauth_state', $query['state']);
        $this->get('/desktop')->assertOk()->assertDontSee('meta-app-secret')->assertSee('data-connection-redirect-uri', false);
    }

    public function test_single_page_callback_connects_facebook_and_linked_instagram_with_one_secret(): void
    {
        app(OAuthAppCredentials::class)->save('meta', ['client_id' => 'meta-app-id', 'client_secret' => 'meta-app-secret']);
        $this->fakeAuthorization([['id' => 'page-1', 'name' => 'Manna', 'access_token' => 'page-secret-token', 'tasks' => ['CREATE_CONTENT'], 'link' => 'https://facebook.com/manna', 'instagram_business_account' => ['id' => 'ig-1', 'username' => 'manna.media']]]);

        $this->withSession(['publishing.meta.oauth_state' => 'state'])
            ->get(route('desktop.publishing.meta.callback').'?state=state&code=oauth-code')
            ->assertRedirect('/desktop?open=settings')->assertSessionHas('status', __('publishing.meta_connected'));

        $store = app(ConnectionStore::class);
        $this->assertSame('page-1', $store->connection('facebook')['external_id']);
        $this->assertSame('ig-1', $store->connection('instagram')['external_id']);
        $this->assertSame('facebook', $store->connection('instagram')['credential_provider']);
        $this->assertSame('page-secret-token', $store->credentials('facebook')['access_token']);
        $this->assertSame('page-secret-token', $store->credentials('instagram')['access_token']);
        $this->assertSame('{}', app(Settings::class)->secret('social_instagram'));
        $this->assertTrue($store->connected('facebook'));
        $this->assertTrue($store->connected('instagram'));
        $this->assertStringNotContainsString('page-secret-token', (string) DB::table('settings')->where('key', 'secret.social_facebook')->value('value'));

        Http::assertSentCount(5);
        $this->get('/desktop')->assertOk()->assertDontSee('page-secret-token')->assertDontSee('oauth-code')
            ->assertSee('data-meta-action="check"', false)->assertSee('data-meta-action="disconnect"', false);
    }

    public function test_multiple_pages_are_selected_in_native_settings_without_exposing_tokens(): void
    {
        app(OAuthAppCredentials::class)->save('meta', ['client_id' => 'meta-app-id', 'client_secret' => 'meta-app-secret']);
        $pages = [
            ['id' => 'page-1', 'name' => 'First Page', 'access_token' => 'first-page-secret', 'tasks' => ['CREATE_CONTENT']],
            ['id' => 'page-2', 'name' => 'Second Page', 'access_token' => 'second-page-secret', 'tasks' => ['MANAGE'], 'instagram_business_account' => ['id' => 'ig-2', 'username' => 'second.ig']],
        ];
        $this->fakeAuthorization($pages, false);

        $this->withSession(['publishing.meta.oauth_state' => 'state'])
            ->get(route('desktop.publishing.meta.callback').'?state=state&code=oauth-code')
            ->assertRedirect('/desktop?open=settings')->assertSessionHas('status', __('publishing.meta_choose_page'));
        $this->get('/desktop')->assertOk()->assertSee('data-meta-page-select', false)->assertSee('First Page')->assertSee('@second.ig')
            ->assertDontSee('first-page-secret')->assertDontSee('second-page-secret');

        Http::fake(['https://graph.facebook.com/v23.0/page-2*' => Http::response([
            'id' => 'page-2', 'name' => 'Second Page', 'tasks' => ['MANAGE'],
            'instagram_business_account' => ['id' => 'ig-2', 'username' => 'second.ig'],
        ])]);
        $response = $this->postJson(route('desktop.publishing.meta.select'), ['page_id' => 'page-2'])
            ->assertOk()->assertJsonPath('status', 'connected')->assertSessionMissing('publishing.meta.oauth_pending');
        $this->assertStringNotContainsString('second-page-secret', $response->getContent());
        $this->assertSame('page-2', app(ConnectionStore::class)->connection('facebook')['external_id']);
    }

    public function test_connection_check_verifies_app_page_permissions_and_instagram_access(): void
    {
        $this->seedConnectedMeta();
        Http::fake([
            'https://graph.facebook.com/v23.0/debug_token*' => Http::sequence()
                ->push(['data' => ['is_valid' => true, 'app_id' => 'meta-app-id', 'expires_at' => now()->addDay()->timestamp, 'scopes' => MetaClient::REQUIRED_SCOPES]])
                ->push(['data' => ['is_valid' => true, 'app_id' => 'meta-app-id', 'expires_at' => now()->addDay()->timestamp, 'scopes' => ['pages_show_list']]])
                ->push(['data' => ['is_valid' => false]]),
            'https://graph.facebook.com/v23.0/page-1*' => Http::response(['id' => 'page-1', 'name' => 'Manna', 'tasks' => ['CREATE_CONTENT'], 'instagram_business_account' => ['id' => 'ig-1', 'username' => 'manna.media']]),
            'https://graph.facebook.com/v23.0/ig-1*' => Http::response(['id' => 'ig-1', 'username' => 'manna.media']),
        ]);

        $this->postJson(route('desktop.publishing.meta.check'))->assertOk()
            ->assertJsonPath('status', 'connected')->assertJsonPath('status_label', __('social.status_connected'))
            ->assertJsonPath('connection.status', 'connected')->assertJsonPath('connection.instagram.id', 'ig-1');
        $this->assertNotEmpty(app(ConnectionStore::class)->connection('facebook')['checked_at']);
        $this->assertSame('connected', app(SocialConnections::class)->editor()['instagram']['status']);

        $this->postJson(route('desktop.publishing.meta.check'))->assertUnprocessable()
            ->assertJsonPath('status', 'error')->assertJsonPath('message', __('publishing.meta_check_permissions'));
        $this->assertSame('error', app(SocialConnections::class)->editor()['facebook']['status']);

        $this->postJson(route('desktop.publishing.meta.check'))->assertUnprocessable()
            ->assertJsonPath('status', 'expired')->assertJsonPath('message', __('publishing.meta_token_expired'));
        $this->assertSame('expired', app(SocialConnections::class)->editor()['facebook']['status']);
        $this->assertSame('expired', app(SocialConnections::class)->editor()['instagram']['status']);
    }

    public function test_failed_oauth_is_user_friendly_and_redacts_code_app_secret_and_tokens(): void
    {
        app(OAuthAppCredentials::class)->save('meta', ['client_id' => 'meta-app-id', 'client_secret' => 'meta-app-secret']);
        app(Settings::class)->update(['social_connections' => ['facebook' => ['provider' => 'facebook', 'configured_at' => now()->toIso8601String()]]]);
        $logs = [];
        Log::listen(function (MessageLogged $event) use (&$logs) { $logs[] = [$event->message, $event->context]; });
        Http::fake([
            'https://graph.facebook.com/v23.0/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short-secret-token'])->push(['access_token' => 'long-secret-token', 'expires_in' => 3600]),
            'https://graph.facebook.com/v23.0/me/permissions*' => Http::response(['data' => [['permission' => 'pages_show_list', 'status' => 'granted']]]),
        ]);

        $this->withSession(['publishing.meta.oauth_state' => 'state'])
            ->get(route('desktop.publishing.meta.callback').'?state=state&code=oauth-secret-code')
            ->assertRedirect('/desktop?open=settings')->assertSessionHas('desktop_error', __('publishing.meta_connection_failed'));

        $log = json_encode($logs, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($logs);
        foreach (['oauth-secret-code', 'meta-app-secret', 'short-secret-token', 'long-secret-token'] as $secret) {
            $this->assertStringNotContainsString($secret, $log);
        }
        $this->assertSame('error', app(SocialConnections::class)->editor()['facebook']['status']);
    }

    public function test_invalid_state_without_a_saved_connection_leaves_social_connections_empty(): void
    {
        $this->withSession(['publishing.meta.oauth_state' => 'expected'])
            ->get(route('desktop.publishing.meta.callback').'?state=wrong&code=ignored')
            ->assertRedirect('/desktop?open=settings')->assertSessionHas('desktop_error', __('publishing.oauth_state_invalid'));

        $this->assertSame([], app(Settings::class)->get('social_connections', []));
        Http::assertNothingSent();
    }

    public function test_meta_routes_require_integration_management_permission(): void
    {
        $editor = User::factory()->create();
        $editor->roles()->attach(Role::where('name', 'Editor')->firstOrFail());
        $this->actingAs($editor);

        $this->get(route('desktop.publishing.meta.connect'))->assertForbidden();
        $this->get(route('desktop.publishing.meta.callback'))->assertForbidden();
        $this->postJson(route('desktop.publishing.meta.select'), ['page_id' => 'page-1'])->assertForbidden();
        $this->postJson(route('desktop.publishing.meta.check'))->assertForbidden();
        $this->postJson(route('desktop.publishing.meta.disconnect'))->assertForbidden();
        Http::assertNothingSent();
    }

    private function fakeAuthorization(array $pages, bool $includePage = true): void
    {
        $responses = [
            'https://graph.facebook.com/v23.0/oauth/access_token*' => Http::sequence()
                ->push(['access_token' => 'short-user-secret'])->push(['access_token' => 'long-user-secret', 'expires_in' => 3600]),
            'https://graph.facebook.com/v23.0/me/permissions*' => Http::response(['data' => collect(MetaClient::REQUIRED_SCOPES)->map(fn ($scope) => ['permission' => $scope, 'status' => 'granted'])->all()]),
            'https://graph.facebook.com/v23.0/me/accounts*' => Http::response(['data' => $pages]),
        ];
        if ($includePage) $responses['https://graph.facebook.com/v23.0/'.$pages[0]['id'].'*'] = Http::response([
            'id' => $pages[0]['id'], 'name' => $pages[0]['name'], 'tasks' => $pages[0]['tasks'], 'link' => $pages[0]['link'] ?? null,
            'instagram_business_account' => $pages[0]['instagram_business_account'] ?? null,
        ]);
        Http::fake($responses);
    }

    private function seedConnectedMeta(): void
    {
        app(OAuthAppCredentials::class)->save('meta', ['client_id' => 'meta-app-id', 'client_secret' => 'meta-app-secret']);
        app(Settings::class)->update(['social_connections' => [
            'facebook' => ['provider' => 'facebook', 'external_id' => 'page-1', 'display_name' => 'Manna'],
            'instagram' => ['provider' => 'instagram', 'external_id' => 'ig-1', 'display_name' => 'manna.media', 'credential_provider' => 'facebook', 'meta_page_id' => 'page-1'],
        ]]);
        app(ConnectionStore::class)->saveCredentials('facebook', ['access_token' => 'page-secret-token', 'scopes' => MetaClient::REQUIRED_SCOPES], true);
    }
}
