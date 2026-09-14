<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InstallationHealthController extends Controller
{
    public function __invoke()
    {
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $error) {
            $checks['database'] = ['status' => 'failed', 'message' => 'Database unavailable.'];
        }

        try {
            $disk = Storage::disk(config('platform.media_disk'));
            $key = '.health/'.bin2hex(random_bytes(12));
            $written = $disk->put($key, 'ok');
            $read = $written && $disk->get($key) === 'ok';
            $disk->delete($key);
            $checks['storage'] = ['status' => $read ? 'ok' : 'failed'];
        } catch (\Throwable) {
            $checks['storage'] = ['status' => 'failed', 'message' => 'Media storage unavailable.'];
        }

        try {
            $checks['owner'] = [
                'status' => User::whereHas('roles', fn ($query) => $query->where('name', 'Owner'))->exists() ? 'ok' : 'failed',
            ];
            $checks['queue'] = [
                'status' => 'ok',
                'pending' => (int) DB::table('jobs')->count(),
                'failed' => (int) DB::table('failed_jobs')->count(),
            ];
        } catch (\Throwable) {
            $checks['owner'] = ['status' => 'failed', 'message' => 'Installation metadata unavailable.'];
            $checks['queue'] = ['status' => 'failed', 'message' => 'Queue metadata unavailable.'];
        }

        $healthy = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'ok' : 'failed',
            'version' => config('platform.version'),
            'checks' => $checks,
        ], $healthy ? 200 : 503)->header('Cache-Control', 'private, no-store');
    }
}
