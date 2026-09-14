<?php

namespace App\Services;

use App\Contracts\SearchProviderInterface;
use App\Models\User;
use Illuminate\Support\Facades\{Cache, DB, Storage};

class InstallationHealth
{
    public function report(): array
    {
        $checks = [
            'runtime' => [
                'status' => 'ok',
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
            ],
            'database' => $this->database(),
            'storage' => $this->storage(),
            'cache' => $this->cache(),
            'owner' => $this->owner(),
            'queue' => $this->queue(),
            'scheduler' => $this->heartbeat('schedule'),
            'queue_worker' => $this->heartbeat('queue'),
            'search' => $this->search(),
        ];

        $healthy = collect($checks)->every(fn (array $check) => $check['status'] !== 'failed');

        return [
            'status' => $healthy ? 'ok' : 'failed',
            'version' => config('platform.version'),
            'checks' => $checks,
        ];
    }

    private function database(): array
    {
        try {
            DB::select('SELECT 1');

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'message' => 'Database unavailable.'];
        }
    }

    private function storage(): array
    {
        try {
            $diskName = (string) config('platform.media_disk');
            $disk = Storage::disk($diskName);
            $key = '.health/'.bin2hex(random_bytes(12));
            $written = $disk->put($key, 'ok');
            $read = $written && $disk->get($key) === 'ok';
            $disk->delete($key);
            if (! $read) return ['status' => 'failed', 'message' => 'Media storage unavailable.'];

            $result = ['status' => 'ok', 'disk' => $diskName];
            $path = config('filesystems.disks.'.$diskName.'.driver') === 'local' ? $disk->path('') : null;
            if (is_string($path) && is_dir($path)) {
                $result['free_bytes'] = disk_free_space($path) ?: null;
                $result['total_bytes'] = disk_total_space($path) ?: null;
            }

            return $result;
        } catch (\Throwable) {
            return ['status' => 'failed', 'message' => 'Media storage unavailable.'];
        }
    }

    private function cache(): array
    {
        try {
            $key = 'platform.health.'.bin2hex(random_bytes(12));
            Cache::put($key, 'ok', now()->addMinute());
            $read = Cache::get($key) === 'ok';
            Cache::forget($key);

            return ['status' => $read ? 'ok' : 'failed'];
        } catch (\Throwable) {
            return ['status' => 'failed', 'message' => 'Cache unavailable.'];
        }
    }

    private function owner(): array
    {
        try {
            return [
                'status' => User::whereHas('roles', fn ($query) => $query->where('name', 'Owner'))->exists() ? 'ok' : 'failed',
            ];
        } catch (\Throwable) {
            return ['status' => 'failed', 'message' => 'Installation metadata unavailable.'];
        }
    }

    private function queue(): array
    {
        try {
            return [
                'status' => 'ok',
                'pending' => (int) DB::table('jobs')->count(),
                'failed' => (int) DB::table('failed_jobs')->count(),
            ];
        } catch (\Throwable) {
            return ['status' => 'failed', 'message' => 'Queue metadata unavailable.'];
        }
    }

    private function heartbeat(string $mode): array
    {
        try {
            $value = Cache::get('platform.health.cron.'.$mode);
            if (! is_string($value) || $value === '') {
                return ['status' => 'unknown', 'message' => 'No successful scheduler heartbeat recorded yet.'];
            }

            $lastRun = now()->parse($value);
            if ($lastRun->lt(now()->subMinutes(10))) {
                return ['status' => 'failed', 'last_run_at' => $lastRun->toIso8601String(), 'message' => 'No recent successful scheduled-task heartbeat.'];
            }

            return ['status' => 'ok', 'last_run_at' => $lastRun->toIso8601String()];
        } catch (\Throwable) {
            return ['status' => 'failed', 'message' => 'Scheduled-task heartbeat unavailable.'];
        }
    }

    private function search(): array
    {
        try {
            $provider = app(SearchProviderInterface::class);

            return ['status' => 'ok', 'driver' => class_basename($provider)];
        } catch (\Throwable) {
            return ['status' => 'failed', 'message' => 'Search provider unavailable.'];
        }
    }
}
