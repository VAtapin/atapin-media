<?php

namespace Tests\Feature;

use App\Models\ImportRun;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use App\Services\Importing\ImportProgress;
use App\Services\Importing\ImportStopped;
use App\Services\Importing\ImportWorkerActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_bytes_are_throttled_across_instances_and_stage_keeps_zip_part(): void
    {
        $run = ImportRun::create(['source'=>'youtube-takeout', 'status'=>'running']);
        app(ImportProgress::class)->checkpoint($run, 'extract', ['part'=>2, 'parts'=>8, 'file'=>'Original.mp4', 'file_total_bytes'=>100, 'file_bytes'=>10], true);
        $this->assertSame(10, $run->fresh()->progress['file_bytes']);
        app(ImportProgress::class)->checkpoint($run, null, ['file_bytes'=>20]);
        $this->assertSame(10, $run->fresh()->progress['file_bytes']);
        $this->travel(6)->seconds();
        app(ImportProgress::class)->checkpoint($run, null, ['file_bytes'=>30]);
        $this->assertSame(30, $run->fresh()->progress['file_bytes']);
        $this->assertSame(now()->toIso8601String(), $run->fresh()->progress['activity_at']);
        app(ImportProgress::class)->checkpoint($run, 'metadata');
        $this->assertSame(2, $run->fresh()->progress['part']);
        $this->assertArrayNotHasKey('file', $run->fresh()->progress);
    }

    public function test_streaming_hash_keeps_sha256_and_can_stop_without_changing_original(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('original.mp4', str_repeat('original', 2 * 1024 * 1024));
        $path = Storage::disk('local')->path('original.mp4');
        $run = ImportRun::create(['source'=>'test', 'status'=>'running']);
        $this->assertSame(hash_file('sha256', $path), app(ImportProgress::class)->hashFile($run, $path, 'verify_file'));
        $this->assertSame(filesize($path), $run->fresh()->progress['file_bytes']);
        $reporter = new class extends ImportProgress {
            public function checkpoint(ImportRun $run, ?string $stage = null, array $details = [], bool $force = false): void
            {
                if (($details['file_bytes'] ?? 0) >= 8 * 1024 * 1024) $run->update(['status'=>'stop_requested']);
                parent::checkpoint($run, $stage, $details, $force);
            }
        };
        try { $reporter->hashFile($run, $path, 'verify_archive'); $this->fail('Hash ignored stop'); }
        catch (ImportStopped) { $this->assertSame(16 * 1024 * 1024, filesize($path)); }
    }

    public function test_worker_metrics_are_measured_not_guessed_and_pid_reuse_resets_delta(): void
    {
        Cache::flush();
        $observer = \Mockery::mock(ImportWorkerActivity::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $observer->shouldReceive('snapshot')->andReturn(
            ['state'=>'observed', 'identity'=>'10:100', 'read'=>100, 'written'=>50, 'cpu'=>1],
            ['state'=>'observed', 'identity'=>'10:100', 'read'=>300, 'written'=>150, 'cpu'=>4],
            ['state'=>'observed', 'identity'=>'10:200', 'read'=>500, 'written'=>300, 'cpu'=>8],
            ['state'=>'unavailable']
        );
        $this->assertSame('observed', $observer->observe(true)['state']);
        $this->travel(60)->seconds();
        $result = $observer->observe(true);
        $this->assertSame('working', $result['state']);
        $this->assertSame(200, $result['read_bytes']);
        $this->assertSame(100, $result['written_bytes']);
        $this->assertSame(60, $result['interval_seconds']);
        $this->assertArrayNotHasKey('identity', $result);
        $this->assertSame($result, $observer->observe()); // HTTP can use the scheduled measurement, without /proc access.
        $this->travel(60)->seconds();
        $this->assertSame('observed', $observer->observe(true)['state']);
        $this->travel(60)->seconds();
        $this->assertSame('unavailable', $observer->observe(true)['state']);
    }

    public function test_only_this_platform_php_queue_process_matches(): void
    {
        $observer = new ImportWorkerActivity;
        $path = base_path('bin/cron.php');
        $this->assertTrue($observer->matches(['/opt/plesk/php/8.4/bin/php','-f',$path,'--','queue'], base_path()));
        $this->assertTrue($observer->matches(['php','bin/cron.php','queue'], base_path()));
        $this->assertFalse($observer->matches(['php','-f',$path,'--','schedule'], base_path()));
        $this->assertFalse($observer->matches(['php','/other-domain/bin/cron.php','queue'], base_path()));
        $this->assertFalse($observer->matches(['/bin/sh','-c','php '.$path.' queue'], base_path()));
        $this->assertFalse($observer->matches(['php-fpm',$path,'queue'], base_path()));
    }

    public function test_running_import_is_visible_before_new_queued_jobs_and_summary_uses_server_time(): void
    {
        app(Access::class)->seed();
        $user = User::factory()->create(); $user->roles()->attach(Role::where('name','Owner')->firstOrFail()); $this->actingAs($user);
        $run = ImportRun::create(['source'=>'youtube-takeout','status'=>'running','updated_at'=>now()->subHours(2)]);
        foreach (range(1,21) as $n) ImportRun::create(['source'=>'intake']);
        $this->getJson('/desktop/imports')->assertOk()->assertJsonPath('data.0.id',$run->id)->assertJsonPath('meta.active_run.id',$run->id)
            ->assertJsonPath('meta.active',22)->assertJsonPath('meta.server_time',now()->toIso8601String())->assertJsonStructure(['meta'=>['worker'=>['state']]]);
    }
}
