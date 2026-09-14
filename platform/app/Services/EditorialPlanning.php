<?php

namespace App\Services;

use App\Models\{PublicationSchedule, SourceRecord, User};
use App\Services\Publishing\{ConnectionStore, ConnectorRegistry, PublishingService};
use Illuminate\Support\Facades\DB;

class EditorialPlanning
{
    public function schedule(SourceRecord $record, User $user, array $providers, string $time): PublicationSchedule
    {
        $time=\Illuminate\Support\Carbon::parse($time,app(Settings::class)->get('system_timezone',config('platform.timezone')))->utc()->format('Y-m-d H:i:s');
        abort_unless(in_array($record->kind,['video','short','post'],true)&&$record->status === 'ready' && !($record->metadata['archive_data'] ?? false) && !($record->metadata['library_only'] ?? false),422,__('workspaces.ready_required'));
        $allowed = ['website', ...array_keys(app(ConnectorRegistry::class)->all())];
        abort_unless($providers && !array_diff($providers,$allowed),422,__('workspaces.invalid_destinations'));
        foreach (array_diff($providers,['website']) as $provider) abort_unless(app(ConnectionStore::class)->connected($provider),422,__('workspaces.disconnected'));
        return DB::transaction(function () use ($record,$user,$providers,$time) {
            $record=$record->newQuery()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->kind,['video','short','post'],true)&&$record->status==='ready'&&!($record->metadata['archive_data']??false)&&!($record->metadata['library_only']??false),422);
            PublicationSchedule::where('source_record_id',$record->id)->where('status','scheduled')->update(['status'=>'cancelled']);
            $schedule = PublicationSchedule::create(['source_record_id'=>$record->id,'user_id'=>$user->id,'source_version'=>$this->version($record),'providers'=>array_values(array_unique($providers)),'publish_at'=>$time]);
            app(Audit::class)->record('publication.scheduled',(string)$schedule->id);
            return $schedule;
        });
    }

    public function dispatchDue(): int
    {
        $count = 0;
        PublicationSchedule::where('status','scheduled')->where('publish_at','<=',now())->chunkById(100,function($rows) use (&$count) {
            foreach ($rows as $row) {
                if (!PublicationSchedule::whereKey($row->id)->where('status','scheduled')->update(['status'=>'queued'])) continue;
                \App\Jobs\PublishScheduledContent::dispatch($row->id)->afterCommit();
                $count++;
            }
        });
        return $count;
    }

    public function publish(PublicationSchedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $schedule = PublicationSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            if ($schedule->status !== 'queued') return;
            $user = User::find($schedule->user_id);
            abort_unless($user?->hasPermission('content.publish'),403);
            $record = SourceRecord::whereKey($schedule->source_record_id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->kind,['video','short','post'],true)&&$record->status === 'ready' && !($record->metadata['archive_data'] ?? false) && !($record->metadata['library_only'] ?? false),422);
            abort_unless($schedule->source_version&&hash_equals($schedule->source_version,$this->version($record)),409,__('workspaces.schedule_stale'));
            $record->updateQuietly(['metadata'=>[...($record->metadata ?? []),'publishing_targets'=>$schedule->providers]]);
            if (in_array('website',$schedule->providers,true)) app(\App\Services\Importing\ContentAssignment::class)->record($record,['public_published'=>true]);
            app(PublishingService::class)->queueForRecord($record->fresh(),$schedule->providers);
            $schedule->update(['status'=>'completed','error'=>null]);
            app(Audit::class)->record('publication.schedule_completed',(string)$schedule->id);
        });
    }
    public function version(SourceRecord $record): string
    {
        $metadata=array_intersect_key($record->metadata??[],array_flip(['tags','short_description','cover_media_id','media_ids','public_section','body_format','author','guest','transcript','seo_title','seo_description','platform_metadata']));
        return hash('sha256',json_encode([$record->title,$record->body,$record->kind,$record->status,$metadata],JSON_THROW_ON_ERROR));
    }
}
