<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class XClient
{
    public const REQUIRED_SCOPES = ['tweet.read', 'tweet.write', 'users.read', 'media.write', 'offline.access'];

    public function __construct(private readonly ConnectionStore $connections) {}

    public function oauthConfigured(): bool
    {
        return app(OAuthAppCredentials::class)->configured('x', false);
    }

    public function oauthClientId(): string
    {
        return app(OAuthAppCredentials::class)->get('x')['client_id'];
    }

    public function request(): PendingRequest
    {
        if (! $this->connections->connected('x')) throw new \RuntimeException('X is disconnected.');
        $credentials = $this->connections->credentials('x');
        if (($credentials['expires_at'] ?? PHP_INT_MAX) <= now()->addMinute()->timestamp) {
            $credentials = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $credentials['refresh_token'] ?? '']);
            if (! $this->connections->connected('x')) throw new \RuntimeException('X was disconnected during refresh.');
            $this->connections->saveCredentials('x', $credentials);
        }
        return Http::withToken($credentials['access_token'] ?? '')->acceptJson()->timeout(120);
    }

    public function token(array $data): array
    {
        $oauth = app(OAuthAppCredentials::class)->get('x');
        $request = Http::asForm()->timeout(30);
        if ($oauth['client_secret'] !== '') $request = $request->withBasicAuth($oauth['client_id'], $oauth['client_secret']);
        $payload = $request->post('https://api.x.com/2/oauth2/token', [...$data, 'client_id' => $oauth['client_id']])->throw()->json();
        if (empty($payload['access_token'])) throw new \RuntimeException('X returned no access token.');
        return [...$payload, 'expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 7200))->timestamp];
    }

    public function check(): array
    {
        if (! $this->oauthConfigured()) throw new \RuntimeException('X OAuth app credentials are missing.');
        $connection = $this->connections->connection('x');
        $credentials = $this->connections->credentials('x');
        $externalId = $connection['external_id'] ?? null;
        $accessToken = $credentials['access_token'] ?? null;
        if (! is_string($externalId) || $externalId === '' || ! is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('X connection is incomplete.');
        }
        if (! empty($connection['revoked_at'])) throw new XConnectionExpired('X connection was revoked.');

        $expiresAt = $credentials['expires_at'] ?? null;
        $refreshNeeded = is_numeric($expiresAt) && (int) $expiresAt <= now()->addMinute()->timestamp;
        if ($refreshNeeded && (! is_string($credentials['refresh_token'] ?? null) || $credentials['refresh_token'] === '')) {
            throw new XConnectionExpired('X access token expired and cannot be refreshed.');
        }

        try {
            $response = $this->request()->get($this->url('users/me'), ['user.fields' => 'id,name,username,protected']);
        } catch (\Throwable $error) {
            if ($refreshNeeded || $this->connections->expired('x')) {
                throw new XConnectionExpired('X access token refresh failed.', 0, $error);
            }
            throw $error;
        }

        if ($response->status() === 401) throw new XConnectionExpired('X rejected the access token.');
        if ($response->status() === 403) throw new XConnectionCheckFailed('permissions', 'X publishing permissions are incomplete.');
        if (! $response->successful()) throw new \RuntimeException('X connection check failed with HTTP '.$response->status().'.');

        $user = $response->json('data', []);
        if (! is_array($user) || ! is_string($user['id'] ?? null) || $user['id'] === '' || ! is_string($user['username'] ?? null) || $user['username'] === '') {
            throw new \RuntimeException('X returned no accessible user profile.');
        }
        if (! hash_equals($externalId, $user['id'])) throw new XConnectionCheckFailed('permissions', 'X account access no longer matches the connected profile.');
        if (($user['protected'] ?? true) !== false) throw new XConnectionCheckFailed('permissions', 'X account is not public.');

        $credentials = $this->connections->credentials('x');
        $scope = $credentials['scope'] ?? $credentials['scopes'] ?? [];
        $scopes = is_string($scope) ? preg_split('/\s+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY) : (is_array($scope) ? array_values(array_filter($scope, 'is_string')) : []);
        if (array_diff(self::REQUIRED_SCOPES, $scopes)) throw new XConnectionCheckFailed('permissions', 'X OAuth scopes are incomplete.');

        $this->clearError();

        return [
            'status' => 'connected',
            'x' => ['id' => $user['id'], 'username' => $user['username']],
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function markError(): void
    {
        $settings = app(\App\Services\Settings::class);
        $social = $settings->get('social_connections', []);
        if (! is_array($social['x'] ?? null)) return;
        $social['x']['last_error_at'] = now()->toIso8601String();
        $settings->update(['social_connections' => $social]);
    }

    public function markExpired(): void
    {
        $settings = app(\App\Services\Settings::class);
        $social = $settings->get('social_connections', []);
        if (! is_array($social['x'] ?? null)) return;
        $social['x']['revoked_at'] = now()->toIso8601String();
        unset($social['x']['last_error_at']);
        $settings->update(['social_connections' => $social]);
    }

    private function clearError(): void
    {
        $settings = app(\App\Services\Settings::class);
        $social = $settings->get('social_connections', []);
        if (! is_array($social['x'] ?? null)) return;
        unset($social['x']['last_error_at']);
        $social['x']['checked_at'] = now()->toIso8601String();
        $settings->update(['social_connections' => $social]);
    }

    public function url(string $path): string { return 'https://api.x.com/2/'.$path; }
}
