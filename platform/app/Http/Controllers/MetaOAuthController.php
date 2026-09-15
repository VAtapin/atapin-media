<?php

namespace App\Http\Controllers;

use App\Services\Publishing\{ConnectionStore, MetaClient, MetaConnectionCheckFailed, MetaConnectionExpired};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaOAuthController extends Controller
{
    public function connect(Request $request, MetaClient $client)
    {
        if (! $client->oauthConfigured()) return $this->oauthReturn(__('publishing.meta_oauth_not_configured'), true);
        $state = bin2hex(random_bytes(24));
        $request->session()->forget('publishing.meta.oauth_pending');
        $request->session()->put('publishing.meta.oauth_state', $state);
        return redirect()->away($client->authorizeUrl($state));
    }

    public function callback(Request $request, MetaClient $client, ConnectionStore $connections)
    {
        $expected = $request->session()->pull('publishing.meta.oauth_state');
        $state = $request->query('state');
        if (! is_string($expected) || $expected === '' || ! is_string($state) || $state === '' || ! hash_equals($expected, $state)) {
            Log::warning('Meta OAuth callback rejected because its state was missing or invalid.', ['user_id' => $request->user()?->id]);
            $client->markError();
            return $this->oauthReturn(__('publishing.oauth_state_invalid'), true);
        }
        if ($request->query('error')) return $this->oauthReturn(__('publishing.meta_oauth_cancelled'));
        $code = $request->query('code');
        if (! is_string($code) || $code === '') return $this->oauthReturn(__('publishing.meta_connection_failed'), true);

        try {
            $pending = $client->authorization($code);
            if (count($pending['pages']) === 1) {
                $client->connectPage($pending, (string) $pending['pages'][0]['id']);
                return $this->oauthReturn(__('publishing.meta_connected'));
            }
            $request->session()->put('publishing.meta.oauth_pending', $pending);
            return $this->oauthReturn(__('publishing.meta_choose_page'));
        } catch (\Throwable $error) {
            $client->markError();
            Log::warning('Meta OAuth connection failed.', [
                'user_id' => $request->user()?->id,
                'error_type' => $error::class,
                'error' => $this->safeError($connections, $error, ['authorization_code' => $code]),
            ]);
            return $this->oauthReturn(__('publishing.meta_connection_failed'), true);
        }
    }

    public function select(Request $request, MetaClient $client, ConnectionStore $connections)
    {
        $data = $request->validate(['page_id' => 'required|string|max:255']);
        $pending = $request->session()->get('publishing.meta.oauth_pending');
        if (! is_array($pending)) return response()->json(['message' => __('publishing.meta_selection_expired')], 409);
        try {
            $connected = $client->connectPage($pending, $data['page_id']);
            $request->session()->forget('publishing.meta.oauth_pending');
            if (! $request->expectsJson()) return $this->oauthReturn(__('publishing.meta_connected'));
            $request->session()->flash('saved_section', 'social');
            $request->session()->flash('status', __('publishing.meta_connected'));
            return response()->json(['status' => 'connected', 'message' => __('publishing.meta_connected'), 'connection' => $connected]);
        } catch (\Throwable $error) {
            $client->markError();
            Log::warning('Meta Page selection failed.', [
                'user_id' => $request->user()?->id,
                'error_type' => $error::class,
                'error' => $this->safeError($connections, $error, $pending),
            ]);
            if (! $request->expectsJson()) return $this->oauthReturn(__('publishing.meta_connection_failed'), true);
            return response()->json(['message' => __('publishing.meta_connection_failed')], 422);
        }
    }

    public function check(Request $request, MetaClient $client, ConnectionStore $connections)
    {
        try {
            return response()->json(['status' => 'connected', 'status_label' => __('social.status_connected'), 'message' => __('publishing.meta_check_succeeded'), 'connection' => $client->check()]);
        } catch (MetaConnectionExpired $error) {
            $client->markExpired();
            Log::warning('Meta connection check found an expired token.', ['user_id' => $request->user()?->id, 'error' => $connections->safeError($error)]);
            return response()->json(['status' => 'expired', 'status_label' => __('social.status_expired'), 'message' => __('publishing.meta_token_expired')], 422);
        } catch (MetaConnectionCheckFailed $error) {
            $client->markError(true);
            Log::warning('Meta connection check found insufficient access.', [
                'user_id' => $request->user()?->id,
                'reason' => $error->reason,
                'error' => $connections->safeError($error),
            ]);
            return response()->json(['status' => 'error', 'status_label' => __('social.status_error'), 'message' => __('publishing.meta_check_'.$error->reason)], 422);
        } catch (\Throwable $error) {
            $client->markError(true);
            Log::warning('Meta connection check failed.', [
                'user_id' => $request->user()?->id,
                'error_type' => $error::class,
                'error' => $connections->safeError($error),
            ]);
            return response()->json(['status' => 'error', 'status_label' => __('social.status_error'), 'message' => __('publishing.meta_check_failed')], 422);
        }
    }

    public function disconnect(Request $request, MetaClient $client)
    {
        $client->disconnect();
        $request->session()->forget('publishing.meta.oauth_pending');
        return response()->json(['status' => 'expired', 'status_label' => __('social.status_expired'), 'message' => __('publishing.meta_disconnected')]);
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
