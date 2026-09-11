<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
class CheckInstallation extends Command
{
    protected $signature='platform:check';
    protected $description='Check SQL, private storage, owner and queue';
    public function handle(): int
    {
        DB::select('SELECT 1');
        $disk=Storage::disk(config('platform.media_disk')); $key='.health/'.bin2hex(random_bytes(12));
        try { if (!$disk->put($key,'ok') || $disk->get($key)!=='ok') throw new \RuntimeException('Storage check failed'); }
        finally { $disk->delete($key); }
        $this->info('SQL and media storage: OK');
        if (!User::whereHas('roles',fn($q)=>$q->where('name','Owner'))->exists()) { $this->warn('Owner missing. Run owner.'); return self::FAILURE; }
        $this->line('Queued: '.DB::table('jobs')->count().'; failed: '.DB::table('failed_jobs')->count());
        return self::SUCCESS;
    }
}
