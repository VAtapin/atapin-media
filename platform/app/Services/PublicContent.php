<?php
namespace App\Services;
use App\Models\Media;
use App\Models\SourceRecord;
use Illuminate\Support\Str;

class PublicContent
{
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return SourceRecord::whereIn('kind',['video','short','post'])->where('status','ready')
            ->where('source','!=','catalog-reset')->where('metadata->public_published',true)
            ->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false))
            ->where(fn($q)=>$q->whereNull('metadata->library_only')->orWhere('metadata->library_only',false));
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
        return ['title'=>$record->title,'excerpt'=>Str::limit($record->body??'',140),
            'url'=>route(in_array($record->kind,['video','short'])?'public.video':'public.article',['slug'=>Str::slug($record->title).'-'.$record->id]),
            'image'=>$image?route('public.media',[$record,$image]):null,
            'meta'=>isset($record->metadata['public_published_at'])?\Illuminate\Support\Carbon::parse($record->metadata['public_published_at'])->format('d.m.Y'):''];
    }
}
