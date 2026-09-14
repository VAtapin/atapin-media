<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class XClient
{
    public function __construct(private readonly ConnectionStore $connections) {}

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
        $request = Http::asForm()->timeout(30);
        if (config('publishing.x.client_secret')) $request = $request->withBasicAuth((string) config('publishing.x.client_id'), (string) config('publishing.x.client_secret'));
        $payload = $request->post('https://api.x.com/2/oauth2/token', [...$data, 'client_id' => config('publishing.x.client_id')])->throw()->json();
        if (empty($payload['access_token'])) throw new \RuntimeException('X returned no access token.');
        return [...$payload, 'expires_at' => now()->addSeconds((int) ($payload['expires_in'] ?? 7200))->timestamp];
    }

    public function url(string $path): string { return 'https://api.x.com/2/'.$path; }
}
