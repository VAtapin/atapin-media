<?php

namespace Tests\Feature;

use App\Models\ImportRun;
use App\Models\Role;
use App\Models\User;
use App\Services\Access;
use App\Services\Importing\ImportAdapter;
use App\Services\Importing\ImportCenter;
use App\Services\Importing\ImportProgress;
use App\Services\Importing\ImportStopped;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ImportControlTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'Owner'): void
    {
        app(Access::class)->seed();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        $this->actingAs($user);
    }

    private function center(callable $callback): ImportCenter
    {
        return new ImportCenter([new class($callback) implements ImportAdapter {
            public function __construct(private $callback) {}
            public function source(): string {return 'test';}
            public function validate(array $input): array {return $input;}
            public function normalize(array $input): array {return $input;}
            public function import(ImportRun $run): void {($this->callback)($run);}
        }]);
    }

    public function test_queued_stop_skips_work_and_retry_preserves_options(): void
    {
        $this->login(); Queue::fake();
        $run = ImportRun::create(['source'=>'test', 'source_options'=>['path'=>'saved']]);
        $this->postJson('/desktop/imports/'.$run->id.'/stop')->assertOk()->assertJsonPath('status','cancelled');
        $this->center(fn () => $this->fail('Cancelled work ran'))->run($run);
        $this->assertNotNull($run->fresh()->finished_at);
        $this->postJson('/desktop/imports/'.$run->id.'/retry')->assertOk();
        $this->assertSame(['path'=>'saved'],$run->fresh()->source_options);
        $this->assertSame([],$run->fresh()->progress);
    }

    public function test_running_stop_is_cooperative_and_keeps_accepted_originals(): void
    {
        $this->login(); Storage::fake('local'); Storage::disk('local')->put('accepted','Original');
        $run = ImportRun::create(['source'=>'test']);
        $this->center(function ($run) {
            app(ImportProgress::class)->checkpoint($run,'files'); $run->increment('imported');
            $this->postJson('/desktop/imports/'.$run->id.'/stop')->assertOk()->assertJsonPath('status','stop_requested');
            app(ImportProgress::class)->checkpoint($run);
            $this->fail('Continued after stop');
        })->run($run);
        $this->assertSame('cancelled',$run->fresh()->status);
        $this->assertSame(1,$run->fresh()->imported);
        $this->assertNull($run->fresh()->error);
        $this->assertTrue(Storage::disk('local')->exists('accepted'));
        $this->getJson('/desktop/imports?status=cancelled')->assertOk()->assertJsonPath('data.0.progress.stage','files');
    }

    public function test_duplicate_worker_does_not_claim_running_or_completed_work(): void
    {
        $run = ImportRun::create(['source'=>'test']); $calls = 0;
        $center = null;
        $center = $this->center(function ($run) use (&$center, &$calls) { $calls++; $center->run($run->fresh()); });
        $center->run($run); $center->run($run->fresh());
        $this->assertSame(1,$calls); $this->assertSame('complete',$run->fresh()->status);
        $this->login(); $this->postJson('/desktop/imports/'.$run->id.'/stop')->assertConflict();
        $this->login('Editor'); $this->postJson('/desktop/imports/'.$run->id.'/stop')->assertForbidden();
        $run->refresh()->update(['status'=>'running']); (new \App\Jobs\ImportArchive($run->id))->failed(null);
        $this->assertSame('failed',$run->fresh()->status); $this->assertNotNull($run->fresh()->finished_at);
    }

    public function test_stop_terminates_download_process_without_waiting_for_completion(): void
    {
        $run = ImportRun::create(['source'=>'test','status'=>'running']);
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('start')->once()->andReturnUsing(fn () => $run->update(['status'=>'stop_requested']));
        $process->shouldReceive('isRunning')->twice()->andReturn(true);
        $process->shouldReceive('stop')->once()->with(1);
        $process->shouldNotReceive('wait');
        $this->expectException(ImportStopped::class);
        app(ImportProgress::class)->download($run,$process);
    }
}
