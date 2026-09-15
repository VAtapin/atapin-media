<?php

namespace App\Services\Publishing;

use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SocialConnections
{
    public static function definitions(): array
    {
        return [
            'youtube' => ['label' => 'YouTube', 'fields' => ['public_url', 'oauth_client_id', 'oauth_client_secret'], 'oauth' => 'desktop.publishing.youtube.connect'],
            'facebook' => ['label' => 'Facebook', 'fields' => ['public_url', 'oauth_client_id', 'oauth_client_secret'], 'oauth' => 'desktop.publishing.meta.connect', 'oauth_provider' => 'meta', 'oauth_callback' => 'desktop.publishing.meta.callback'],
            'instagram' => ['label' => 'Instagram', 'fields' => ['public_url'], 'oauth' => 'desktop.publishing.meta.connect', 'oauth_provider' => 'meta', 'oauth_callback' => 'desktop.publishing.meta.callback'],
            'telegram' => ['label' => 'Telegram', 'fields' => ['public_url', 'external_id', 'api_key', 'bot_username', 'mini_app_enabled']],
            'x' => ['label' => 'X', 'fields' => ['public_url', 'oauth_client_id', 'oauth_client_secret'], 'oauth' => 'desktop.publishing.x.connect'],
            'tiktok' => ['label' => 'TikTok', 'fields' => ['public_url']],
            'linkedin' => ['label' => 'LinkedIn', 'fields' => ['public_url']],
        ];
    }

    public static function tokenKeys(string $provider): array
    {
        return match ($provider) {
            'youtube' => ['access_token', 'refresh_token'],
            'facebook', 'instagram', 'x' => ['access_token'],
            'telegram' => ['api_key', 'access_token'],
            default => [],
        };
    }

    public function editor(): array
    {
        $connections = app(ConnectionStore::class);
        $oauth = app(OAuthAppCredentials::class);

        return collect(self::definitions())->map(function ($definition, $provider) use ($connections, $oauth) {
            $labels = [];
            foreach ($definition['fields'] as $field) $labels[$field] = __('social.'.$provider.'.'.$field);
            $connection = $connections->connection($provider);
            $connected = $connections->connected($provider);
            $oauthProvider = $definition['oauth_provider'] ?? $provider;
            $oauthCredentials = isset($definition['oauth']) ? $oauth->get($oauthProvider) : [];
            $oauthSecretRequired = $oauthProvider !== 'x';
            $oauthConfigured = isset($definition['oauth']) && $oauth->configured($oauthProvider, $oauthSecretRequired);
            $hasConfiguration = isset($definition['oauth']) ? $oauthConfigured : collect($connection)
                ->except(['provider', 'configured_at', 'revoked_at'])
                ->contains(fn ($value) => $value !== null && $value !== '' && $value !== false);
            $status = ! empty($connection['last_error_at'])
                ? (($connection['last_error_reason'] ?? null) === 'permission' ? 'permission' : 'error')
                : ($connected ? 'connected' : ($connections->expired($provider) ? 'expired' : ($hasConfiguration ? 'configured' : 'not_configured')));

            return [...$definition, 'labels' => $labels, 'hint' => __('social.'.$provider.'.hint'),
                'oauth_url' => isset($definition['oauth']) ? route($definition['oauth']) : null,
                'oauth_redirect_uri' => isset($definition['oauth']) ? route($definition['oauth_callback'] ?? 'desktop.publishing.'.$provider.'.callback') : null,
                'oauth_configured' => $oauthConfigured,
                'oauth_client_id_saved' => ($oauthCredentials['client_id'] ?? '') !== '',
                'oauth_client_secret_saved' => ($oauthCredentials['client_secret'] ?? '') !== '',
                'oauth_client_secret_required' => $oauthSecretRequired,
                'connected' => $connected,
                'status' => $status,
                'status_label' => __('social.status_'.$status),
                'check_url' => match (true) {
                    in_array($provider, ['facebook', 'instagram'], true) => route('desktop.publishing.meta.check'),
                    $provider === 'youtube' => route('desktop.publishing.youtube.check'),
                    $provider === 'x' => route('desktop.publishing.x.check'),
                    $provider === 'telegram' => route('desktop.publishing.telegram.check'),
                    default => null,
                },
                'disconnect_url' => match (true) {
                    in_array($provider, ['facebook', 'instagram'], true) => route('desktop.publishing.meta.disconnect'),
                    $provider === 'youtube' => route('desktop.publishing.youtube.disconnect'),
                    $provider === 'x' => route('desktop.publishing.x.disconnect'),
                    $provider === 'telegram' => route('desktop.publishing.telegram.disconnect'),
                    default => null,
                },
            ];
        })->all();
    }

    public function rules(Request $request): array
    {
        $provider = $request->input('provider', '');
        if (! is_string($provider)) $provider = '';
        $fields = self::definitions()[$provider]['fields'] ?? [];
        $rules = ['provider' => ['required', 'string', Rule::in(array_keys(self::definitions()))]];
        foreach (['public_url', 'external_id', 'api_key', 'oauth_client_id', 'oauth_client_secret', 'access_token', 'webhook_secret', 'bot_username', 'mini_app_enabled'] as $field) {
            $rules[$field] = in_array($field, $fields, true) ? match ($field) {
                'public_url' => 'nullable|url|max:1000',
                'external_id' => 'nullable|string|max:255',
                'oauth_client_id', 'oauth_client_secret' => 'nullable|string|max:4000',
                'bot_username' => ['nullable', 'string', 'max:32', 'regex:/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/'],
                'mini_app_enabled' => 'sometimes|boolean',
                default => 'nullable|string|max:4000',
            } : 'prohibited';
        }
        if ($provider === 'telegram') {
            $store = app(ConnectionStore::class);
            $miniAppOnly = $request->boolean('mini_app_enabled') && ! $request->filled('external_id') && empty($store->connection($provider)['external_id']);
            if (! $miniAppOnly) {
                $rules['external_id'] = empty($store->connection($provider)['external_id']) ? 'required|string|max:255' : 'sometimes|required|string|max:255';
                $hasToken = (bool) array_intersect_key(array_filter($store->credentials($provider)), array_flip(self::tokenKeys($provider)));
                $rules['api_key'] = $hasToken ? 'nullable|string|max:4000' : 'required|string|max:4000';
            }
            if ($request->boolean('mini_app_enabled')) $rules['bot_username'][0] = 'required';
        }
        if (in_array($provider, ['youtube', 'x', 'facebook'], true)) {
            $oauthProvider = $provider === 'facebook' ? 'meta' : $provider;
            $stored = app(OAuthAppCredentials::class)->get($oauthProvider);
            $rules['oauth_client_id'] = [Rule::requiredIf(($stored['client_id'] ?? '') === ''), 'nullable', 'string', 'max:4000'];
            $rules['oauth_client_secret'] = [Rule::requiredIf($provider !== 'x' && ($stored['client_secret'] ?? '') === ''), 'nullable', 'string', 'max:4000'];
        }
        return $rules;
    }

    public function save(array $values, Settings $settings): bool
    {
        return DB::transaction(function () use ($values, $settings): bool {
            $provider = $values['provider'];
            $connections = $settings->get('social_connections', []);
            $connection = $connections[$provider] ?? [];
            $metadata = array_intersect_key($values, array_flip(['public_url', 'external_id', 'bot_username', 'mini_app_enabled']));
            $next = [...$connection, ...$metadata];
            $hasMetadata = collect($next)->except(['provider', 'configured_at', 'checked_at', 'last_error_at', 'last_error_reason', 'revoked_at'])
                ->contains(fn ($value) => $value !== null && $value !== '' && $value !== false);
            if (! $hasMetadata && ! in_array($provider, ['youtube', 'x', 'facebook'], true)) {
                if ($connection) {
                    unset($connections[$provider]);
                    $settings->update(['social_connections' => $connections]);
                }
                return false;
            }
            $connections[$provider] = [...$next, 'provider' => $provider, 'configured_at' => now()->toIso8601String()];
            // Editing a profile URL does not reconnect an explicitly revoked account.
            $credentials = array_filter(array_intersect_key($values, array_flip(['api_key', 'access_token'])), fn ($value) => is_string($value) && $value !== '');
            if ($credentials) {
                unset($connections[$provider]['revoked_at']);
                app(ConnectionStore::class)->saveCredentials($provider, $credentials);
            }
            if (in_array($provider, ['youtube', 'x', 'facebook'], true)) {
                app(OAuthAppCredentials::class)->save($provider === 'facebook' ? 'meta' : $provider, [
                    'client_id' => $values['oauth_client_id'] ?? null,
                    'client_secret' => $values['oauth_client_secret'] ?? null,
                ]);
            }
            $settings->update(['social_connections' => $connections]);
            return true;
        });
    }
}
