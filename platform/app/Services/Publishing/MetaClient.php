<?php

namespace App\Services\Publishing;

use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class MetaClient
{
    public const REQUIRED_SCOPES = [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_posts',
        'instagram_basic',
        'instagram_content_publish',
    ];

    public function __construct(
        private readonly ConnectionStore $connections,
        private readonly OAuthAppCredentials $oauth,
        private readonly Settings $settings,
    ) {
    }

    public function oauthConfigured(): bool
    {
        return $this->oauth->configured('meta');
    }

    public function authorizeUrl(string $state): string
    {
        $credentials = $this->oauth->get('meta');

        return rtrim((string) config('publishing.meta.oauth_authorize'), '/').'?'.http_build_query([
            'client_id' => $credentials['client_id'],
            'redirect_uri' => route('desktop.publishing.meta.callback'),
            'response_type' => 'code',
            'scope' => implode(',', self::REQUIRED_SCOPES),
            'state' => $state,
        ]);
    }

    public function authorization(string $code): array
    {
        $credentials = $this->oauth->get('meta');
        $short = Http::acceptJson()->timeout(30)->get((string) config('publishing.meta.oauth_token'), [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'redirect_uri' => route('desktop.publishing.meta.callback'),
            'code' => $code,
        ]);
        $shortToken = $short->successful() ? $short->json('access_token') : null;
        if (! is_string($shortToken) || $shortToken === '') throw new \RuntimeException('Meta OAuth token exchange failed.');

        $long = Http::acceptJson()->timeout(30)->get((string) config('publishing.meta.oauth_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'fb_exchange_token' => $shortToken,
        ]);
        $token = $long->successful() ? $long->json('access_token') : null;
        if (! is_string($token) || $token === '') throw new \RuntimeException('Meta long-lived token exchange failed.');

        $expiresIn = (int) $long->json('expires_in', 0);
        $expiresAt = $expiresIn > 0 ? now()->addSeconds($expiresIn)->timestamp : null;
        $permissions = Http::acceptJson()->withToken($token)->timeout(30)
            ->get($this->url('me/permissions'))->throw()->json('data', []);
        $scopes = collect($permissions)->filter(fn ($permission) => ($permission['status'] ?? null) === 'granted')
            ->pluck('permission')->filter(fn ($permission) => is_string($permission))->values()->all();
        $missing = array_values(array_diff(self::REQUIRED_SCOPES, $scopes));
        if ($missing) throw new \RuntimeException('Meta permissions missing: '.implode(', ', $missing));

        $pages = Http::acceptJson()->withToken($token)->timeout(30)->get($this->url('me/accounts'), [
            'fields' => 'id,name,access_token,tasks,link,instagram_business_account{id,username}',
            'limit' => 100,
        ])->throw()->json('data', []);
        $pages = collect($pages)->filter(function ($page) {
            return is_string($page['id'] ?? null) && $page['id'] !== ''
                && is_string($page['access_token'] ?? null) && $page['access_token'] !== ''
                && $this->canPublish($page['tasks'] ?? []);
        })->map(fn ($page) => [
            'id' => $page['id'],
            'name' => is_string($page['name'] ?? null) ? $page['name'] : $page['id'],
            'access_token' => $page['access_token'],
            'tasks' => array_values(array_filter($page['tasks'] ?? [], 'is_string')),
            'link' => is_string($page['link'] ?? null) ? $page['link'] : null,
            'instagram' => $this->instagram($page['instagram_business_account'] ?? null),
        ])->values()->all();
        if (! $pages) throw new \RuntimeException('Meta returned no Page with content publishing access.');

        return ['pages' => $pages, 'scopes' => $scopes, 'expires_at' => $expiresAt];
    }

    public function connectPage(array $pending, string $pageId): array
    {
        $page = collect($pending['pages'] ?? [])->first(fn ($candidate) => is_array($candidate) && hash_equals((string) ($candidate['id'] ?? ''), $pageId));
        if (! is_array($page) || ! is_string($page['access_token'] ?? null) || ! $this->canPublish($page['tasks'] ?? [])) {
            throw new \RuntimeException('The selected Meta Page is unavailable.');
        }

        $verified = Http::acceptJson()->withToken($page['access_token'])->timeout(30)->get($this->url($pageId), [
            'fields' => 'id,name,link,instagram_business_account{id,username}',
        ])->throw()->json();
        if (($verified['id'] ?? null) !== $pageId) throw new \RuntimeException('Meta did not confirm Page access.');

        $instagram = $this->instagram($verified['instagram_business_account'] ?? null);
        $scopes = array_values(array_intersect(self::REQUIRED_SCOPES, array_filter($pending['scopes'] ?? [], 'is_string')));
        $expiresAt = (int) ($pending['expires_at'] ?? 0);
        $now = now()->toIso8601String();
        $social = $this->settings->get('social_connections', []);
        $social['facebook'] = array_filter([
            'provider' => 'facebook',
            'external_id' => $pageId,
            'display_name' => is_string($verified['name'] ?? null) ? $verified['name'] : $page['name'],
            'public_url' => is_string($verified['link'] ?? null) ? $verified['link'] : ($page['link'] ?? 'https://www.facebook.com/'.$pageId),
            'configured_at' => $now,
            'expires_at' => $expiresAt ?: null,
            'scopes' => $scopes,
        ]);
        if ($instagram) {
            $social['instagram'] = [
                'provider' => 'instagram',
                'external_id' => $instagram['id'],
                'display_name' => $instagram['username'] ?: $instagram['id'],
                'public_url' => $instagram['username'] ? 'https://www.instagram.com/'.rawurlencode($instagram['username']).'/' : null,
                'credential_provider' => 'facebook',
                'meta_page_id' => $pageId,
                'configured_at' => $now,
                'expires_at' => $expiresAt ?: null,
                'scopes' => $scopes,
            ];
        } elseif (isset($social['instagram']) && ($social['instagram']['credential_provider'] ?? null) === 'facebook') {
            $social['instagram'] = [...$social['instagram'], 'external_id' => null, 'meta_page_id' => $pageId, 'revoked_at' => $now];
        }

        DB::transaction(function () use ($page, $expiresAt, $scopes, $social) {
            $this->connections->saveCredentials('facebook', array_filter([
                'access_token' => $page['access_token'],
                'expires_at' => $expiresAt ?: null,
                'scopes' => $scopes,
            ]), true);
            // Instagram publishing uses the same Page token. Never persist a
            // second copy of it under social_instagram.
            $this->settings->updateSecrets(['social_instagram' => '{}']);
            $this->settings->update(['social_connections' => $social]);
        });

        return ['facebook' => $social['facebook'], 'instagram' => $instagram ? $social['instagram'] : null];
    }

    public function check(): array
    {
        if (! $this->oauthConfigured()) throw new \RuntimeException('Meta OAuth app credentials are missing.');
        $facebook = $this->connections->connection('facebook');
        $credentials = $this->connections->credentials('facebook');
        $pageId = $facebook['external_id'] ?? null;
        $token = $credentials['access_token'] ?? null;
        if (! is_string($pageId) || $pageId === '' || ! is_string($token) || $token === '') throw new \RuntimeException('Meta Page connection is incomplete.');
        if ($this->connections->expired('facebook')) throw new MetaConnectionExpired('Meta access token expired.');

        $oauth = $this->oauth->get('meta');
        $debug = Http::acceptJson()->timeout(30)->get($this->url('debug_token'), [
            'input_token' => $token,
            'access_token' => $oauth['client_id'].'|'.$oauth['client_secret'],
        ]);
        if (! $debug->successful() || $debug->json('data.is_valid') !== true || (string) $debug->json('data.app_id') !== $oauth['client_id']) {
            throw new MetaConnectionExpired('Meta rejected the Page access token.');
        }
        $debugExpires = (int) $debug->json('data.expires_at', 0);
        if ($debugExpires > 0 && $debugExpires <= now()->timestamp) throw new MetaConnectionExpired('Meta access token expired.');
        $debugScopeData = $debug->json('data.scopes', []);
        $debugScopes = is_array($debugScopeData) ? array_values(array_filter($debugScopeData, fn ($scope) => is_string($scope))) : [];
        if (array_diff(self::REQUIRED_SCOPES, $debugScopes)) throw new MetaConnectionCheckFailed('permissions', 'Meta publishing permissions are incomplete.');

        $page = Http::acceptJson()->withToken($token)->timeout(30)->get($this->url($pageId), [
            'fields' => 'id,name,link,instagram_business_account{id,username}',
        ])->throw()->json();
        if (($page['id'] ?? null) !== $pageId) throw new MetaConnectionCheckFailed('page_access', 'Meta Page access is missing.');

        $instagramConnection = $this->connections->connection('instagram');
        $instagram = $this->instagram($page['instagram_business_account'] ?? null);
        if (is_string($instagramConnection['external_id'] ?? null) && $instagramConnection['external_id'] !== '') {
            if (! $instagram || $instagram['id'] !== $instagramConnection['external_id']) throw new MetaConnectionCheckFailed('instagram_access', 'The connected Instagram professional account is no longer linked to this Page.');
            $profile = Http::acceptJson()->withToken($token)->timeout(30)->get($this->url($instagram['id']), ['fields' => 'id,username'])->throw()->json();
            if (($profile['id'] ?? null) !== $instagram['id']) throw new MetaConnectionCheckFailed('instagram_access', 'Instagram professional account access is missing.');
        }

        $this->clearError();
        return [
            'status' => 'connected',
            'facebook' => ['id' => $pageId, 'name' => $page['name'] ?? $facebook['display_name'] ?? $pageId],
            'instagram' => $instagram ? ['id' => $instagram['id'], 'username' => $instagram['username']] : null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function markError(bool $connectedAccountsToo = false): void
    {
        $social = $this->settings->get('social_connections', []);
        $original = $social;
        foreach (['facebook', 'instagram'] as $provider) {
            if (! isset($social[$provider]) || ! is_array($social[$provider])) continue;
            if (! $connectedAccountsToo && $this->connections->connected($provider)) continue;
            $social[$provider] = [...$social[$provider], 'provider' => $provider, 'last_error_at' => now()->toIso8601String()];
        }
        if ($social !== $original) $this->settings->update(['social_connections' => $social]);
    }

    public function markExpired(): void
    {
        $social = $this->settings->get('social_connections', []);
        foreach (['facebook', 'instagram'] as $provider) {
            if (! isset($social[$provider])) continue;
            $social[$provider]['revoked_at'] = now()->toIso8601String();
            unset($social[$provider]['last_error_at']);
        }
        $this->settings->update(['social_connections' => $social]);
    }

    public function disconnect(): void
    {
        $this->connections->forgetCredentials('facebook');
        $this->connections->forgetCredentials('instagram');
        $this->markExpired();
    }

    private function clearError(): void
    {
        $social = $this->settings->get('social_connections', []);
        foreach (['facebook', 'instagram'] as $provider) {
            if (! isset($social[$provider])) continue;
            unset($social[$provider]['last_error_at']);
            $social[$provider]['checked_at'] = now()->toIso8601String();
        }
        $this->settings->update(['social_connections' => $social]);
    }

    private function canPublish(mixed $tasks): bool
    {
        return is_array($tasks) && (in_array('CREATE_CONTENT', $tasks, true) || in_array('MANAGE', $tasks, true));
    }

    private function instagram(mixed $value): ?array
    {
        if (! is_array($value) || ! is_string($value['id'] ?? null) || $value['id'] === '') return null;
        return ['id' => $value['id'], 'username' => is_string($value['username'] ?? null) ? $value['username'] : null];
    }

    private function url(string $resource): string
    {
        return rtrim((string) config('publishing.meta.api_base'), '/').'/'.trim($resource, '/');
    }
}
