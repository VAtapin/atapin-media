<?php
namespace App\Services;
use App\Models\{Media,SourceRecord,User};
use Illuminate\Support\Facades\DB;
class RecordedPodcast
{
    public function draft(User $user,SourceRecord $event,Media $audio): SourceRecord
    {
        abort_unless(($event->metadata['public_section']??null)==='live',404);
        abort_unless($audio->kind==='audio'&&!$audio->archived_at&&in_array($audio->mime,['audio/wav','audio/x-wav'],true)
            &&(int)$audio->user_id===$user->id&&app(MediaOriginalLocator::class)->find($audio),422,__('live-browser.audio_unavailable'));
        return DB::transaction(function()use($event,$audio){
            $record=SourceRecord::withTrashed()->firstOrCreate(['source'=>'manual','source_id'=>'browser-podcast:'.$audio->id],
                ['kind'=>'video','title'=>$event->title,'body'=>$event->body,'status'=>'unsorted',
                    'metadata'=>['public_section'=>'podcast','public_published'=>false,'classification_origin'=>'manual','media_ids'=>[$audio->id],'recorded_from_live_id'=>$event->id]]);
            abort_if($record->trashed(),409,__('live-browser.podcast_trashed'));
            app(\App\Services\Importing\LocalMediaLinks::class)->repair($record);
            if($record->wasRecentlyCreated)app(Audit::class)->record('podcast.browser_draft_created',(string)$record->id);
            return $record->fresh();
        });
    }
}
