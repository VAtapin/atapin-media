<?php
namespace App\Services;
use App\Models\Media;
use App\Models\SourceRecord;
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
        return $this->query()->whereIn('kind',['video','short'])->where('metadata->public_homepage',true);
    }
    public function latestRecording(): ?SourceRecord
    {
        return $this->forSection('live')->where('metadata->live_status','ended')->latest()->first();
    }
    public function nextLive(): ?SourceRecord
    {
        return $this->forSection('live')->whereIn('metadata->live_status',['live','scheduled'])->get()->sortBy(function(SourceRecord $record){
            $status=$record->metadata['live_status']??null;$starts=$record->metadata['starts_at']??null;$time=$starts?strtotime((string)$starts):PHP_INT_MAX;
            return [$status==='live'?0:1,$time,(int)$record->id];
        })->first();
    }
    public function children(SourceRecord $record,string $kind='comment'): \Illuminate\Database\Eloquent\Builder
    {
        return $this->query()->where('source',$record->source)->where('metadata->parent_source_id',$record->source_id)->where('kind',$kind);
    }
    public function visible(SourceRecord $record): bool
    {
        if(!$this->query()->whereKey($record->id)->exists())return false;
        if(in_array($record->kind,['comment','live_chat']))return $this->query()->where('source',$record->source)->where('source_id',$record->metadata['parent_source_id']??'')->whereIn('kind',['video','short','post'])->exists();
        return true;
    }
    public function assets(SourceRecord $record): \Illuminate\Support\Collection
    {
        return Media::whereNull('archived_at')->whereIn('id',app(\App\Services\Importing\LocalMediaLinks::class)->ids($record))
            ->whereIn('mime',['image/jpeg','image/png','image/webp','image/gif','video/mp4','video/webm','audio/mpeg','audio/ogg','application/pdf'])
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
            $parent=$this->query()->where('source',$record->source)->where('source_id',$record->metadata['parent_source_id']??'')->whereIn('kind',['video','short','post'])->first();
            if($parent)$url=$this->card($parent)['url'].($record->kind==='live_chat'?'#chat':'#comments');
        }
        $meta=$section==='live'&&($record->metadata['starts_at']??null)
            ?\Illuminate\Support\Carbon::parse($record->metadata['starts_at'])->format('d.m.Y H:i')
            :(isset($record->metadata['public_published_at'])?\Illuminate\Support\Carbon::parse($record->metadata['public_published_at'])->format('d.m.Y'):'');
        $date=$section==='live'&&($record->metadata['starts_at']??null)?$meta:($record->metadata['starts_at']??'');
        return ['id'=>$record->id,'kind'=>$record->kind,'section'=>$section,'title'=>$record->title,'excerpt'=>Str::limit($record->body??'',140),
            'url'=>$url,'author'=>is_string($author)?$author:($author['name']??''),
            'tags'=>array_values(array_filter($record->metadata['tags']??[],'is_string')),
            'duration'=>$record->metadata['duration']??null,'views'=>is_numeric($record->metadata['views']??null)?(int)$record->metadata['views']:null,
            'date'=>$date,'viewers'=>is_numeric($record->metadata['viewer_count']??null)?(int)$record->metadata['viewer_count']:'',
            'image'=>$image?route('public.media',[$record,$image]):null,
            'meta'=>$meta];
    }
}
