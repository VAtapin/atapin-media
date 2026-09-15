<?php

namespace App\Http\Controllers;

use App\Services\Publishing\{ConnectionStore, XClient, XConnectionCheckFailed, XConnectionExpired};
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http, Log};

class XOAuthController extends Controller
{
    public function connect(Request $request, XClient $client)
    {
        if (! $client->oauthConfigured()) {
            return $this->oauthReturn(__('publishing.x_oauth_not_configured'), true);
        }
        $clientId = $client->oauthClientId();
        $state = bin2hex(random_bytes(24));
        $verifier = bin2hex(random_bytes(32));
        $request->session()->put('publishing.x.oauth', ['state' => $state, 'verifier' => $verifier]);
        return redirect()->away('https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => route('desktop.publishing.x.callback'),
            'scope' => implode(' ', XClient::REQUIRED_SCOPES), 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
        ]));
    }

    public function callback(Request $request, XClient $client, ConnectionStore $connections, Settings $settings)
    {
        $expected = $request->session()->pull('publishing.x.oauth');
        $state = $request->query('state');
        $expectedState = is_array($expected) ? ($expected['state'] ?? null) : null;
        $verifier = is_array($expected) ? ($expected['verifier'] ?? null) : null;
        if (! is_string($expectedState) || $expectedState === '' || ! is_string($verifier) || $verifier === '' || ! is_string($state) || $state === '' || ! hash_equals($expectedState, $state)) {
            Log::warning('X OAuth callback rejected because its state was missing or invalid.', ['user_id' => $request->user()?->id]);
            return $this->oauthReturn(__('publishing.oauth_state_invalid'), true);
        }
        if ($request->query('error')) return $this->oauthReturn(__('publishing.x_oauth_cancelled'));
        $code = $request->query('code');
        if (! is_string($code) || $code === '') return $this->oauthReturn(__('publishing.x_connection_failed'), true);

        $credentials = [];
        try {
            $credentials = $client->token(['grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => route('desktop.publishing.x.callback')]);
            $user = Http::withToken($credentials['access_token'])->timeout(30)->get($client->url('users/me'), ['user.fields' => 'protected'])->throw()->json('data', []);
            if (! is_string($user['id'] ?? null) || $user['id'] === '' || ! is_string($user['username'] ?? null) || $user['username'] === '') {
                throw new \RuntimeException('X returned no accessible user profile.');
            }
            if (($user['protected'] ?? true) !== false) throw new \RuntimeException('X account must be public.');

            $social = $settings->get('social_connections', []);
            $social['x'] = ['provider' => 'x', 'external_id' => $user['id'], 'public_url' => 'https://x.com/'.rawurlencode($user['username']), 'configured_at' => now()->toIso8601String()];
            DB::transaction(function () use ($settings, $connections, $credentials, $social) {
                $connections->saveCredentials('x', $credentials, true);
                $settings->update(['social_connections' => $social]);
            });
        } catch (\Throwable $error) {
            Log::warning('X OAuth connection failed.', [
                'user_id' => $request->user()?->id,
                'error_type' => $error::class,
                'error' => $this->safeError($connections, $error, [...$credentials, 'authorization_code' => $code, 'code_verifier' => $verifier]),
            ]);
            return $this->oauthReturn(__('publishing.x_connection_failed'), true);
        }

        return $this->oauthReturn(__('publishing.x_connected'));
    }

    public function check(Request $request, XClient $client, ConnectionStore $connections)
    {
        try {
            return response()->json([
                'status' => 'connected',
                'status_label' => __('social.status_connected'),
                'message' => __('publishing.x_check_succeeded'),
                'connection' => $client->check(),
            ]);
        } catch (XConnectionExpired $error) {
            $client->markExpired();
            Log::warning('X connection check found an expired token.', [
                'user_id' => $request->user()?->id,
                'error' => $connections->safeError($error),
            ]);
            return response()->json([
                'status' => 'expired',
                'status_label' => __('social.status_expired'),
                'message' => __('publishing.x_token_expired'),
            ], 422);
        } catch (XConnectionCheckFailed $error) {
            $client->markError();
            Log::warning('X connection check found insufficient access.', [
                'user_id' => $request->user()?->id,
                'reason' => $error->reason,
                'error' => $connections->safeError($error),
            ]);
            return response()->json([
                'status' => 'error',
                'status_label' => __('social.status_error'),
                'message' => __('publishing.x_check_'.$error->reason),
            ], 422);
        } catch (\Throwable $error) {
            $client->markError();
            Log::warning('X connection check failed.', [
                'user_id' => $request->user()?->id,
                'error_type' => $error::class,
                'error' => $connections->safeError($error),
            ]);
            return response()->json([
                'status' => 'error',
                'status_label' => __('social.status_error'),
                'message' => __('publishing.x_check_failed'),
            ], 422);
        }
    }

    private function oauthReturn(string $message, bool $error = false)
    {
        return redirect('/desktop?open=settings')->with('saved_section', 'social')->with($error ? 'desktop_error' : 'status', $message);
    }

    private function safeError(ConnectionStore $connections, \Throwable $error, array $credentials): string
    {
        $message = $connections->safeError($error);
        array_walk_recursive($credentials, static function ($value) use (&$message) {
            if (is_string($value) && strlen($value) >= 4) $message = str_replace([$value, rawurlencode($value)], '[redacted]', $message);
        });
        return mb_substr($message, 0, 4000);
    }
}
