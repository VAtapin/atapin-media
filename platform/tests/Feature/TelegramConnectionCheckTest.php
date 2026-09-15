<?php

namespace Tests\Feature;

use App\Models\{Role, User};
use App\Services\{Access, Settings};
use App\Services\Publishing\{ConnectionStore, SocialConnections};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramConnectionCheckTest extends TestCase
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
        app(Settings::class)->update(['social_connections' => ['telegram' => [
            'provider' => 'telegram',
            'external_id' => '@manna_channel',
            'configured_at' => now()->toIso8601String(),
        ]]]);
        app(ConnectionStore::class)->saveCredentials('telegram', ['api_key' => 'telegram-test-token']);
    }

    public function test_check_uses_only_read_only_bot_api_methods_and_records_success(): void
    {
        Http::fake([
            'https://api.telegram.org/bottelegram-test-token/getMe' => Http::response(['ok' => true, 'result' => ['id' => 123, 'username' => 'MannaBot']]),
            'https://api.telegram.org/bottelegram-test-token/getChat' => Http::response(['ok' => true, 'result' => ['id' => -100123, 'type' => 'channel', 'username' => 'manna_channel', 'title' => 'Manna']]),
            'https://api.telegram.org/bottelegram-test-token/getChatMember' => Http::response(['ok' => true, 'result' => ['status' => 'administrator', 'can_post_messages' => true]]),
        ]);

        $response = $this->postJson(route('desktop.publishing.telegram.check'))->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('connection.bot_username', 'MannaBot')
            ->assertJsonPath('connection.chat_username', 'manna_channel');

        $this->assertStringNotContainsString('telegram-test-token', $response->getContent());
        $connection = app(Settings::class)->get('social_connections')['telegram'];
        $this->assertNotEmpty($connection['checked_at']);
        $this->assertArrayNotHasKey('last_error_at', $connection);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => collect(['getMe', 'getChat', 'getChatMember'])->contains(fn ($method) => str_ends_with($request->url(), '/'.$method)));
        Http::assertNotSent(fn (Request $request) => preg_match('~/(send|edit|delete|set)[A-Z]~', $request->url()) === 1);
    }

    public function test_failed_check_is_safe_and_sets_visible_error_state(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

        $response = $this->postJson(route('desktop.publishing.telegram.check'))->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->assertStringNotContainsString('telegram-test-token', $response->getContent());
        $this->assertNotEmpty(app(Settings::class)->get('social_connections')['telegram']['last_error_at']);
        $this->assertSame('error', app(SocialConnections::class)->editor()['telegram']['status']);
        Http::assertSentCount(1);
    }

    public function test_disconnect_revokes_credentials_without_calling_telegram(): void
    {
        $this->postJson(route('desktop.publishing.telegram.disconnect'))->assertOk()->assertJsonPath('status', 'expired');

        $this->assertFalse(app(ConnectionStore::class)->connected('telegram'));
        $this->assertNotEmpty(app(Settings::class)->get('social_connections')['telegram']['revoked_at']);
        Http::assertNothingSent();
    }
}
