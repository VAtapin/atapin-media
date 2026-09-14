<?php
namespace App\Services;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PublicContent
{
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return SourceRecord::whereIn('kind',['video','short','post','poll','comment','live_chat'])->where('status','ready')
            ->where('source','!=','catalog-reset')->where('metadata->public_published',true)
            ->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false))
            ->where(fn($q)=>$q->whereNull('metadata->library_only')->orWhere('metadata->library_only',false));
    }
    public function section(SourceRecord $record): string
    {
        $section=$record->metadata['public_section']??null;
        return in_array($section,['videos','beitraege','podcast','live','community'],true)?$section:
            (in_array($record->kind,['video','short'])?'videos':($record->kind==='post'?'beitraege':'community'));
    }
    public function forSection(string $section): \Illuminate\Database\Eloquent\Builder
    {
        $query=$this->query();
        if(in_array($section,['videos','beitraege']))return $query->whereIn('kind',$section==='videos'?['video','short']:['post'])
            ->where(fn($q)=>$q->whereNull('metadata->public_section')->orWhere('metadata->public_section',$section));
        if($section==='community')return $query->whereIn('kind',['post','poll'])->where(fn($q)=>$q->where('metadata->public_section','community')->orWhere(fn($q)=>$q->where('kind','poll')->whereNull('metadata->public_section')));
        return $query->whereIn('kind',['video','short','post'])->where('metadata->public_section',$section);
    }
    public function homepageVideos(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->forSection('videos')->where('metadata->public_homepage',true);
    }
    public function latestRecording(): ?SourceRecord
    {
        return $this->forSection('live')->where('metadata->live_status','ended')->latest()->first();
    }
    public function withViewCounts(Builder $query): Builder
    {
        return $query->withCount(['publicViews as public_view_count']);
    }
    public function viewCount(SourceRecord $record): int
    {
        if (array_key_exists('public_view_count', $record->getAttributes())) return (int) $record->public_view_count;
        return (int) $record->publicViews()->count();
    }
    public function hasFutureStart(SourceRecord $record): bool
    {
        $starts = $record->metadata['starts_at'] ?? null;
        if (!$starts) return false;
        try { return Carbon::parse((string) $starts)->timezone(config('app.timezone'))->isFuture(); }
        catch (\Throwable) { return false; }
    }
    public function isMissedScheduled(SourceRecord $record): bool
    {
        return ($record->metadata['live_status'] ?? null) === 'scheduled'
            && !$this->hasFutureStart($record);
    }
    public function expireScheduledLives(): int
    {
        $expired = SourceRecord::where('metadata->public_section','live')->where('metadata->live_status','scheduled')->get()
            ->filter(fn(SourceRecord $record)=>$this->isMissedScheduled($record));
        foreach ($expired as $record) {
            $metadata = $record->metadata ?? [];
            $metadata['live_status'] = 'ended';
            $metadata['ended_reason'] = 'missed_schedule';
            $metadata['ended_at'] = now(config('app.timezone'))->toIso8601String();
            $record->update(['metadata'=>$metadata]);
        }
        return $expired->count();
    }
    public function nextLive(): ?SourceRecord
    {
        $this->expireScheduledLives();
        return $this->forSection('live')->whereIn('metadata->live_status',['live','scheduled'])->get()->filter(function(SourceRecord $record){
            return ($record->metadata['live_status'] ?? null) === 'live' || $this->hasFutureStart($record);
        })->sortBy(function(SourceRecord $record){
            $status=$record->metadata['live_status']??null;$starts=$record->metadata['starts_at']??null;$time=$starts?strtotime((string)$starts):PHP_INT_MAX;
            return [$status==='live'?0:1,$time,(int)$record->id];
        })->first();
    }
    public function children(SourceRecord $record,string $kind='comment'): \Illuminate\Database\Eloquent\Builder
    {
        return $this->query()->where(fn($q)=>$q->where(fn($legacy)=>$legacy->where('source',$record->source)->where('metadata->parent_source_id',$record->source_id))->orWhere('metadata->parent_record_id',$record->id))->where('kind',$kind);
    }
    public function childrenForViewer(SourceRecord $record,string $kind,?User $user,?string $sessionId): \Illuminate\Database\Eloquent\Builder
    {
        $query=SourceRecord::where('source','!=','catalog-reset')
            ->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false))
            ->where(fn($q)=>$q->whereNull('metadata->library_only')->orWhere('metadata->library_only',false))
            ->where(fn($q)=>$q->where(fn($legacy)=>$legacy->where('source',$record->source)->where('metadata->parent_source_id',$record->source_id))->orWhere('metadata->parent_record_id',$record->id))->where('kind',$kind)
            ->where(fn($q)=>$q->where(fn($public)=>$public->where('status','ready')->where('metadata->public_published',true)));

        if($user||$sessionId){
            $query->orWhere(fn($owned)=>$owned->where('source','!=','catalog-reset')
                ->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false))
                ->where(fn($q)=>$q->whereNull('metadata->library_only')->orWhere('metadata->library_only',false))
                ->where(fn($q)=>$q->where(fn($legacy)=>$legacy->where('source',$record->source)->where('metadata->parent_source_id',$record->source_id))->orWhere('metadata->parent_record_id',$record->id))->where('kind',$kind)
                ->where('status','needs_attention')->where(function($q)use($user,$sessionId){
                    if($user)$q->where('metadata->author_user_id',$user->id);
                    else $q->where('metadata->author_session_hash',hash('sha256',$sessionId));
                }));
        }
        return $query;
    }
    public function isOwnedByViewer(SourceRecord $record,?User $user,?string $sessionId): bool
    {
        $metadata=$record->metadata??[];
        if($user&&isset($metadata['author_user_id']))return (int)$metadata['author_user_id']===$user->id;
        return !$user&&$sessionId&&($metadata['author_session_hash']??null)===hash('sha256',$sessionId);
    }
    public function visible(SourceRecord $record): bool
    {
        if(!$this->query()->whereKey($record->id)->exists())return false;
        if(in_array($record->kind,['comment','live_chat']))return $this->query()->where(fn($q)=>$q->where(fn($legacy)=>$legacy->where('source',$record->source)->where('source_id',$record->metadata['parent_source_id']??''))->orWhere('id',$record->metadata['parent_record_id']??0))->whereIn('kind',['video','short','post'])->exists();
        return true;
    }
    public function assets(SourceRecord $record): \Illuminate\Support\Collection
    {
        return Media::whereNull('archived_at')->whereIn('id',app(\App\Services\Importing\LocalMediaLinks::class)->ids($record))
            ->whereIn('mime',['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm','audio/mpeg','audio/ogg','audio/mp4','audio/wav','audio/x-wav','audio/flac','application/pdf'])
            ->get()->filter(fn($media)=>app(MediaOriginalLocator::class)->find($media));
    }
    public function card(SourceRecord $record): array
    {
        $assets=$this->assets($record);
        $image=$assets->first(fn($media)=>$media->kind==='image'&&$media->id===($record->metadata['cover_media_id']??null))
            ??$assets->first(fn($media)=>$media->kind==='image'&&$media->asset_role==='thumbnail')
            ??$assets->first(fn($media)=>$media->kind==='image');
        $section=$this->section($record);$author=$record->metadata['author']??null;
        $url=match($section){'videos'=>route('public.video',['slug'=>Str::slug($record->title).'-'.$record->id]),
            'beitraege'=>route('public.article',['slug'=>Str::slug($record->title).'-'.$record->id]),
            'podcast'=>route('public.podcast',['episode'=>$record->id]),'live'=>route('public.live',['event'=>$record->id]),
            default=>route('public.community',['discussion'=>$record->id])};
        if(in_array($record->kind,['comment','live_chat'])){
            $parent=$this->query()->where(fn($q)=>$q->where(fn($legacy)=>$legacy->where('source',$record->source)->where('source_id',$record->metadata['parent_source_id']??''))->orWhere('id',$record->metadata['parent_record_id']??0))->whereIn('kind',['video','short','post'])->first();
            if($parent)$url=$this->card($parent)['url'].($record->kind==='live_chat'?'#chat':'#comments');
        }
        $meta=$section==='live'&&($record->metadata['starts_at']??null)
            ?\Illuminate\Support\Carbon::parse($record->metadata['starts_at'])->format('d.m.Y H:i')
            :(isset($record->metadata['public_published_at'])?\Illuminate\Support\Carbon::parse($record->metadata['public_published_at'])->format('d.m.Y'):'');
        $date=$section==='live'&&($record->metadata['starts_at']??null)?$meta:($record->metadata['starts_at']??'');
        $project=app(Settings::class);
        $editorial=in_array($record->kind,['video','short'],true)||in_array($section,['beitraege','podcast'],true);
        return ['id'=>$record->id,'kind'=>$record->kind,'section'=>$section,'title'=>$record->title,'excerpt'=>$editorial?($record->metadata['short_description']??''):Str::limit($record->body??'',140),
            'url'=>$url,'author'=>($editorial?$project->get('public_author_name',''):'') ?: (is_string($author)?$author:($author['name']??'')),
            'author_image'=>$project->get('public_author_image'),
            'tags'=>array_values(array_filter($record->metadata['tags']??[],'is_string')),
            'duration'=>$record->metadata['duration']??null,'views'=>$this->viewCount($record),
            'live_status'=>$section==='live'?($record->metadata['live_status']??null):null,
            'date'=>$date,'viewers'=>is_numeric($record->metadata['viewer_count']??null)?(int)$record->metadata['viewer_count']:'',
            'image'=>$image?($image->publicUrl() ?? route('public.media',[$record,$image])):null,
            'meta'=>$meta];
    }
}
