<?php

namespace App\Services\Importing;

use Illuminate\Support\Facades\Cache;

/** Read-only observation, including workers started before progress reporting was deployed. */
class ImportWorkerActivity
{
    public function observe(bool $scheduled = false): array
    {
        $key = 'import-worker-observation:'.hash('sha256', base_path());
        if (! $scheduled && ($recent = Cache::get($key.':scheduled')) && now()->timestamp - $recent['at'] < 75) return $recent['public'];
        $previous = Cache::get($key);
        if (! $scheduled && $previous && now()->timestamp - $previous['at'] < 5) return $previous['public'];
        $sample = $this->snapshot();
        $public = ['state' => $sample['state'], 'observed_at' => now()->toIso8601String()];
        if ($sample['state'] === 'observed' && $previous && ($previous['sample']['identity'] ?? null) === $sample['identity']) {
            $read = max(0, $sample['read'] - $previous['sample']['read']);
            $written = max(0, $sample['written'] - $previous['sample']['written']);
            $cpu = max(0, $sample['cpu'] - $previous['sample']['cpu']);
            $public += ['read_bytes' => $read, 'written_bytes' => $written, 'interval_seconds' => now()->timestamp - $previous['at']];
            $public['state'] = ($read || $written || $cpu) ? 'working' : 'observed';
        }
        Cache::put($key, ['at' => now()->timestamp, 'sample' => $sample, 'public' => $public], 120);
        if ($scheduled) Cache::put($key.':scheduled', ['at' => now()->timestamp, 'public' => $public], 90);
        return $public;
    }

    protected function snapshot(): array
    {
        if (PHP_OS_FAMILY !== 'Linux' || ! @is_readable('/proc/self/stat')) return ['state' => 'unavailable'];
        $uid = @fileowner('/proc/self');
        if ($uid === false) return ['state' => 'unavailable'];
        $denied = false;
        foreach (@glob('/proc/[0-9]*', GLOB_ONLYDIR) ?: [] as $directory) {
            if ((int)basename($directory) === getmypid()) continue; // A skipped tick is an observer, not the worker.
            if (@fileowner($directory) !== $uid) continue;
            $raw = @file_get_contents($directory.'/cmdline');
            if ($raw === false) { $denied = true; continue; }
            $args = explode("\0", rtrim($raw, "\0"));
            if (! $this->matches($args, @readlink($directory.'/cwd') ?: '')) continue;
            $stat = @file_get_contents($directory.'/stat');
            $io = @file_get_contents($directory.'/io');
            if ($stat === false || $io === false) { $denied = true; continue; }
            // The process name in parentheses may itself contain spaces.
            $fields = preg_split('/\s+/', trim(substr($stat, strrpos($stat, ')') + 1)));
            if (count($fields) < 20) continue;
            preg_match_all('/^(rchar|wchar):\s*(\d+)$/m', $io, $values, PREG_SET_ORDER);
            $counts = array_column($values, 2, 1);
            if (! isset($counts['rchar'], $counts['wchar'])) continue;
            return ['state' => 'observed', 'identity' => basename($directory).':'.$fields[19],
                'cpu' => (int)$fields[11] + (int)$fields[12], 'read' => (int)$counts['rchar'], 'written' => (int)$counts['wchar']];
        }
        return ['state' => $denied ? 'unavailable' : 'not_found'];
    }

    public function matches(array $args, string $cwd): bool
    {
        if (! preg_match('/^php(?:[0-9.]+)?$/', basename($args[0] ?? ''))) return false;
        $script = array_search('-f', $args, true);
        $script = $script === false ? ($args[1] ?? '') : ($args[$script + 1] ?? '');
        $absolute = str_starts_with($script, '/') || (strlen($script) > 2 && $script[1] === ':');
        $path = $absolute ? $script : rtrim($cwd, '/').'/'.$script;
        return realpath($path) === realpath(base_path('bin/cron.php')) && end($args) === 'queue';
    }
}
