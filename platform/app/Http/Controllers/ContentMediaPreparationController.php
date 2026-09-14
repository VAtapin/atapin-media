<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use Illuminate\Http\Request;
class ContentMediaPreparationController extends Controller
{
    public function store(Request $request,SourceRecord $record)
    {
        $data=$request->validate(['operation'=>'required|in:frame,podcast,ai_cover']);
        abort_unless(in_array($record->kind,['video','short','post'],true)&&!($record->metadata['archive_data']??false),422);
        $operation=$data['operation'];
        if($operation==='ai_cover')abort_unless(app(\App\Services\ContentShortDescriptions::class)->available(),422,__('imports.ai_unavailable'));
        if($operation==='podcast')abort_unless(($record->metadata['public_section']??null)==='podcast',422);
        if($operation==='frame')abort_unless(app(\App\Services\MediaDerivatives::class)->video($record),422);
        $state=$record->metadata['media_jobs'][$operation]??[];
        if(in_array($state['state']??null,['queued','running'],true)&&strtotime($state['updated_at']??'')>time()-2400)return response()->json(['status'=>'queued']);
        $meta=$record->metadata;$meta['media_jobs'][$operation]=['state'=>'queued','updated_at'=>now()->toIso8601String()];$record->updateQuietly(['metadata'=>$meta]);
        if($operation==='ai_cover')dispatch(new \App\Jobs\GenerateContentCover($record->id,$meta['cover_media_id']??null,app(\App\Services\ContentShortDescriptions::class)->version($record)));
        else dispatch(new \App\Jobs\PrepareContentMedia($record->id,$operation));
        return response()->json(['status'=>'queued']);
    }
}
