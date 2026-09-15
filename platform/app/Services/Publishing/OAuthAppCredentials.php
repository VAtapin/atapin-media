<?php

namespace App\Services\Publishing;

use App\Services\Settings;

final class OAuthAppCredentials
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function get(string $provider): array
    {
        return [
            'client_id' => trim((string) ($this->settings->secret('oauth_'.$provider.'_client_id') ?? '')),
            'client_secret' => trim((string) ($this->settings->secret('oauth_'.$provider.'_client_secret') ?? '')),
        ];
    }

    public function configured(string $provider, bool $secretRequired = true): bool
    {
        $credentials = $this->get($provider);
        return $credentials['client_id'] !== '' && (! $secretRequired || $credentials['client_secret'] !== '');
    }

    public function save(string $provider, array $credentials): void
    {
        $values = [];
        foreach (['client_id', 'client_secret'] as $key) {
            if (is_string($credentials[$key] ?? null) && trim($credentials[$key]) !== '') {
                $values['oauth_'.$provider.'_'.$key] = trim($credentials[$key]);
            }
        }
        if ($values) $this->settings->updateSecrets($values);
    }
}
