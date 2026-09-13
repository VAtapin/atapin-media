<?php

namespace App\Services\Publishing;

use App\Services\Settings;

class ConnectionStore
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function connection(string $provider): array
    {
        $connections = $this->settings->get('social_connections', []);
        return is_array($connections[$provider] ?? null) ? $connections[$provider] : [];
    }

    public function credentials(string $provider): array
    {
        $raw = $this->settings->secret('social_'.$provider);
        if (! is_string($raw) || $raw === '') return [];
        try {
            $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : ['access_token' => $raw];
        } catch (\Throwable) {
            return ['access_token' => $raw];
        }
    }

    public function connected(string $provider): bool
    {
        return (bool) $this->connection($provider) && (bool) $this->credentials($provider);
    }

    public function saveCredentials(string $provider, array $credentials): void
    {
        $current = $this->credentials($provider);
        $merged = array_filter([...$current, ...$credentials], static fn ($value) => $value !== null && $value !== '');
        $this->settings->updateSecrets(['social_'.$provider => json_encode($merged, JSON_THROW_ON_ERROR)]);
        $connections = $this->settings->get('social_connections', []);
        if (is_array($connections) && isset($connections[$provider]['revoked_at'])) {
            unset($connections[$provider]['revoked_at']);
            $this->settings->update(['social_connections' => $connections]);
        }
    }

    public function forgetCredentials(string $provider): void
    {
        // Settings intentionally has no destructive secret API. Marking a connection revoked
        // prevents new work while preserving an audit-safe record for the owner.
        $connections = $this->settings->get('social_connections', []);
        if (isset($connections[$provider])) {
            $connections[$provider]['revoked_at'] = now()->toIso8601String();
            $this->settings->update(['social_connections' => $connections]);
        }
    }

    public function publicConnections(): array
    {
        $connections = $this->settings->get('social_connections', []);
        if (! is_array($connections)) return [];
        return collect($connections)->map(function ($connection, $provider) {
            return [
                'provider' => $provider,
                'external_id' => $connection['external_id'] ?? null,
                'public_url' => $connection['public_url'] ?? null,
                'connected' => $this->connected((string) $provider),
                'revoked' => isset($connection['revoked_at']),
            ];
        })->values()->all();
    }
}
