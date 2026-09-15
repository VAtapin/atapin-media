<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Http;

class MediaDeliveryCheck
{
    public function run(Media $media, ?string $url = null): array
    {
        $result = ['ok' => true];
        $locator = app(MediaOriginalLocator::class);
        $location = $locator->find($media);
        if (! $location) return ['ok' => false, 'local' => ['status' => 'failed', 'bytes' => 0], 'technical' => ['status' => 'failed', 'message' => 'Media original is unavailable.'], 'http' => ['status' => 'skipped']];

        $path = $locator->path($location);
        $bytes = is_file($path) ? (int) filesize($path) : 0;
        $localOk = $bytes > 0 && ($media->bytes === null || $bytes === (int) $media->bytes);
        $result['local'] = ['status' => $localOk ? 'ok' : 'failed', 'bytes' => $bytes];
        $result['ok'] = $localOk;

        try {
            $result['technical'] = ['status' => 'ok', 'facts' => app(MediaTechnicalProbe::class)->inspect($media)];
        } catch (\Throwable) {
            $result['technical'] = ['status' => 'failed', 'message' => 'ffprobe or media inspection unavailable.'];
            $result['ok'] = false;
        }

        if (! is_string($url) || $url === '') {
            $result['http'] = ['status' => 'skipped', 'message' => 'Media has no public URL.'];
            return $result;
        }

        $parts = parse_url($url);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || ! is_string($parts['host'] ?? null) || ! is_string($appHost) || ! hash_equals($appHost, $parts['host'])) {
            $result['http'] = ['status' => 'failed', 'message' => 'URL must use the configured installation host.'];
            $result['ok'] = false;
            return $result;
        }

        $started = microtime(true);
        try {
            $response = Http::withOptions(['allow_redirects' => false, 'proxy' => '', 'stream' => true])
                ->withHeaders(['Range' => 'bytes=0-1048575', 'Accept' => $media->mime ?: '*/*'])
                ->timeout(30)->get($url);
            $body = $response->toPsrResponse()->getBody();
            $sample = $body->read(1024 * 1024);
            $body->close();
            $contentRange = (string) $response->header('Content-Range');
            $acceptRanges = strtolower((string) $response->header('Accept-Ranges'));
            $rangeOk = $response->status() === 206 && str_contains($acceptRanges, 'bytes')
                && preg_match('/^bytes 0-\d+\/\d+$/', $contentRange) === 1;
            $result['http'] = ['status' => $rangeOk ? 'ok' : 'failed', 'http_status' => $response->status(),
                'range' => $rangeOk ? 'ok' : 'unsupported', 'sample_bytes' => strlen($sample),
                'first_read_ms' => (int) round((microtime(true) - $started) * 1000),
                'content_range' => $contentRange ?: null, 'accept_ranges' => $acceptRanges ?: null];
            $result['ok'] = $result['ok'] && $rangeOk;
        } catch (\Throwable) {
            $result['http'] = ['status' => 'failed', 'message' => 'Installation URL unavailable.'];
            $result['ok'] = false;
        }

        return $result;
    }
}
