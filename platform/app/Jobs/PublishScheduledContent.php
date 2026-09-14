<?php

namespace App\Jobs;

use App\Models\PublicationSchedule;
use App\Services\EditorialPlanning;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishScheduledContent implements ShouldQueue
{
    use Queueable;
    public int $tries = 1;
    public function __construct(public int $scheduleId) {}
    public function handle(EditorialPlanning $planning): void
    {
        $schedule = PublicationSchedule::find($this->scheduleId);
        if (!$schedule) return;
        try { $planning->publish($schedule); }
        catch (\Throwable) { PublicationSchedule::whereKey($schedule->id)->where('status','queued')->update(['status'=>'failed','error'=>__('workspaces.schedule_failed')]); }
    }
    public function failed(?\Throwable $error): void { PublicationSchedule::whereKey($this->scheduleId)->where('status','queued')->update(['status'=>'failed','error'=>__('workspaces.schedule_failed')]); }
}
