<?php
namespace App\Services\Importing;
use App\Models\ImportRun;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class ServiceLinkAdapter implements ImportAdapter
{
    private const HOSTS = [
        'youtube-service' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
        'tiktok' => ['www.tiktok.com', 'tiktok.com'],
        'instagram' => ['www.instagram.com', 'instagram.com'],
        'facebook-video' => ['www.facebook.com', 'facebook.com', 'm.facebook.com'],
    ];
    public function __construct(private string $source) {}
    public function source(): string { return $this->source; }
    public function validate(array $input): array
    {
        $url = parse_url($input['source_ref'] ?? '');
        if (! is_array($url) || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass'])
            || isset($url['port']) || ! in_array(strtolower($url['host'] ?? ''), self::HOSTS[$this->source], true)) {
            throw ValidationException::withMessages(['source_ref' => __('imports.invalid_url')]);
        }
        return $input;
    }
    public function normalize(array $input): array
    {
        return ['source' => $this->source, 'source_kind' => 'service-link', 'source_ref' => $input['source_ref'],
            'source_options' => ['url' => $input['source_ref']], 'target_profile' => $input['target_profile'] ?? 'mixed'];
    }
    public function import(ImportRun $run): void
    {
        $url = $this->validate(['source_ref' => $run->source_options['url']])['source_ref'];
        $root = config('platform.import_inbox_root').'/links/'.hash('sha256', $this->source.'|'.$url);
        if (! is_dir($root)) mkdir($root, 0700, true);
        $process = new Process([
            config('platform.import_python'), '-m', 'yt_dlp', '--ignore-config', '--no-plugin-dirs',
            '--use-extractors', 'youtube.*,tiktok.*,instagram.*,facebook.*',
            '--js-runtimes', 'node:'.config('platform.import_node'), '--socket-timeout', '30', '--retries', '3',
            '--concurrent-fragments', '4', '--max-filesize', (string) config('platform.media_upload_max_bytes'),
            '--playlist-end', '10000', '--write-info-json', '--write-thumbnail', '--write-subs', '--write-comments',
            '--sub-langs', 'all,-live_chat', '--format', 'best[ext=mp4]/best', '--no-overwrites',
            '--match-filters', '!is_live & !is_upcoming', '--output', $root.'/%(id)s/item.%(ext)s', '--', $url,
        ]);
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if ($this->source === 'youtube-service' && preg_match('#^/(?:@[^/]+|channel/[^/]+|c/[^/]+|user/[^/]+)/?$#', $path)) {
            $process = new Process([config('platform.import_python'), base_path('../youtube/service_import.py'),
                '--url', $url, '--output', $root, '--node', config('platform.import_node')]);
        }
        $process->setTimeout(3300);
        $lock = fopen($root.'/.import.lock', 'c+b');
        if (! $lock) throw new \RuntimeException('Storage unavailable.');
        if (! flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); throw new \RuntimeException('Source import is already running.'); }
        try {
        app(ImportProgress::class)->download($run, $process);
        // Even a partial service response can contain useful completed originals.
        (new LocalFolderAdapter)->importDirectory($run, $root);
        if (! $process->isSuccessful()) {
            $message = mb_substr(trim($process->getErrorOutput()), -2000);
            if (! $run->fresh()->imported) throw new \RuntimeException($message ?: 'Service import failed.');
            $run->update(['notes' => [...($run->fresh()->notes ?? []), $message ?: 'Service returned partial content.']]);
        }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
}
