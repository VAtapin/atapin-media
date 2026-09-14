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
        if (str_starts_with($provider, 'rtmp_')) {
            $output = $this->liveOutputs()[$provider] ?? null;
            return $output ? ['provider' => $provider, 'label' => $output['label'], 'external_id' => $provider] : [];
        }
        $connections = $this->settings->get('social_connections', []);
        return is_array($connections[$provider] ?? null) ? $connections[$provider] : [];
    }

    public function credentials(string $provider): array
    {
        if (str_starts_with($provider, 'rtmp_')) return $this->liveOutputs()[$provider] ?? [];
        $raw = $this->settings->secret('social_'.$provider);
        if (! is_string($raw) || $raw === '') return [];
        try {
            $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : ['access_token' => $raw];
        } catch (\Throwable) {
            return ['access_token' => $raw];
        }
    }

    public function liveOutputs(): array
    {
        $raw = $this->settings->secret('live_outputs');
        return $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : [];
    }

    public function saveLiveOutput(string $id, string $label, string $url): void
    {
        $outputs = $this->liveOutputs();
        $outputs[$id] = ['label' => $label, 'url' => $url];
        $this->settings->updateSecrets(['live_outputs' => json_encode($outputs, JSON_THROW_ON_ERROR)]);
    }

    public function removeLiveOutput(string $id): void
    {
        $outputs = $this->liveOutputs();
        unset($outputs[$id]);
        $this->settings->updateSecrets(['live_outputs' => json_encode($outputs, JSON_THROW_ON_ERROR)]);
    }

    public function connected(string $provider): bool
    {
        return (bool) $this->connection($provider)
            && empty($this->connection($provider)['revoked_at'])
            && (bool) $this->credentials($provider);
    }

    public function saveCredentials(string $provider, array $credentials, bool $replace = false): void
    {
        $current = $replace ? [] : $this->credentials($provider);
        $merged = array_filter([...$current, ...$credentials], static fn ($value) => $value !== null && $value !== '');
        $this->settings->updateSecrets(['social_'.$provider => json_encode($merged, JSON_THROW_ON_ERROR)]);
    }

    public function forgetCredentials(string $provider): void
    {
        $this->settings->updateSecrets(['social_'.$provider => '{}']);
        $connections = $this->settings->get('social_connections', []);
        if (isset($connections[$provider])) {
            $connections[$provider]['revoked_at'] = now()->toIso8601String();
            $this->settings->update(['social_connections' => $connections]);
        }
    }

    public function safeError(\Throwable $error): string
    {
        $message = $error->getMessage();
        $secrets = [(string) config('publishing.youtube.client_secret'), (string) config('publishing.x.client_secret')];
        foreach ($this->publicConnections() as $connection) {
            $credentials = $this->credentials($connection['provider']);
            array_walk_recursive($credentials, static function ($value) use (&$secrets) {
                if (is_string($value) && strlen($value) >= 4) $secrets[] = $value;
            });
        }
        foreach (array_filter($secrets) as $secret) {
            $message = str_replace([$secret, rawurlencode($secret)], '[redacted]', $message);
        }
        $message = preg_replace('~(/bot)[^/\s]+~', '$1[redacted]', $message);
        $message = preg_replace('~((?:access_token|refresh_token|client_secret|password|Authorization)["\s:=]+)[^\s&,"}]+~i', '$1[redacted]', $message);
        return mb_substr($message, 0, 4000);
    }

    public function publicConnections(): array
    {
        $connections = $this->settings->get('social_connections', []);
        if (! is_array($connections)) return [];
        return [...collect($connections)->map(function ($connection, $provider) {
            return [
                'provider' => $provider,
                'external_id' => $connection['external_id'] ?? null,
                'public_url' => $connection['public_url'] ?? null,
                'connected' => $this->connected((string) $provider),
                'revoked' => isset($connection['revoked_at']),
            ];
        })->values()->all(), ...collect($this->liveOutputs())->map(fn ($output, $provider) => [
            'provider' => $provider, 'label' => $output['label'], 'external_id' => $provider, 'public_url' => null, 'connected' => true, 'revoked' => false,
        ])->values()->all()];
    }
}
