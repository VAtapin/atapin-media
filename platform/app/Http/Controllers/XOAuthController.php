<?php

namespace App\Http\Controllers;

use App\Services\Publishing\{ConnectionStore, XClient};
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http};

class XOAuthController extends Controller
{
    public function connect(Request $request, XClient $client)
    {
        abort_unless($client->oauthConfigured(), 503);
        $clientId = $client->oauthClientId();
        $state = bin2hex(random_bytes(24));
        $verifier = bin2hex(random_bytes(32));
        $request->session()->put('publishing.x.oauth', ['state' => $state, 'verifier' => $verifier]);
        return redirect()->away('https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => route('desktop.publishing.x.callback'),
            'scope' => 'tweet.read tweet.write users.read media.write offline.access', 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
        ]));
    }

    public function callback(Request $request, XClient $client, ConnectionStore $connections, Settings $settings)
    {
        $expected = $request->session()->pull('publishing.x.oauth');
        $state = $request->query('state');
        abort_unless(is_array($expected) && is_string($state) && $state !== '' && hash_equals($expected['state'], $state), 419);
        if ($request->query('error')) return redirect('/desktop')->with('status', __('publishing.oauth_cancelled'));
        $data = $request->validate(['code' => 'required|string']);
        $credentials = $client->token(['grant_type' => 'authorization_code', 'code' => $data['code'], 'code_verifier' => $expected['verifier'], 'redirect_uri' => route('desktop.publishing.x.callback')]);
        $user = Http::withToken($credentials['access_token'])->timeout(30)->get($client->url('users/me'), ['user.fields' => 'protected'])->throw()->json('data', []);
        abort_unless(! empty($user['id']) && ($user['protected'] ?? true) === false, 422, 'X account must be public.');
        DB::transaction(function () use ($settings, $connections, $credentials, $user) {
            $social = $settings->get('social_connections', []);
            $social['x'] = ['provider' => 'x', 'external_id' => $user['id'], 'public_url' => 'https://x.com/'.rawurlencode($user['username']), 'configured_at' => now()->toIso8601String()];
            $connections->saveCredentials('x', $credentials, true);
            $settings->update(['social_connections' => $social]);
        });
        return redirect('/desktop')->with('status', __('publishing.saved'));
    }
}
