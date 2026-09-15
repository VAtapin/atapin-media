<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaDeliveryCheck;
use Illuminate\Console\Command;

class CheckMediaDelivery extends Command
{
    protected $signature = 'platform:media-check {media : Media UUID} {--url= : Same-installation public URL to test with HTTP Range}';
    protected $description = 'Check one media file, technical metadata and bounded HTTP Range delivery';

    public function handle(MediaDeliveryCheck $checker): int
    {
        $media = Media::find($this->argument('media'));
        if (! $media) {
            $this->error('Media not found.');
            return self::FAILURE;
        }

        $url = $this->option('url') ?: $media->publicUrl();
        $report = $checker->run($media, is_string($url) ? $url : null);
        $this->line('Local: '.strtoupper($report['local']['status']).' ('.$report['local']['bytes'].' bytes)');
        $this->line('Technical: '.strtoupper($report['technical']['status']).($report['technical']['status'] === 'ok' ? ' '.json_encode($report['technical']['facts'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : '');
        $http = $report['http'];
        $this->line('HTTP: '.strtoupper($http['status']).($http['status'] === 'ok' ? ' (range='.$http['range'].', sample_bytes='.$http['sample_bytes'].', first_read_ms='.$http['first_read_ms'].')' : ' ('.($http['message'] ?? 'Range unsupported').')');

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
