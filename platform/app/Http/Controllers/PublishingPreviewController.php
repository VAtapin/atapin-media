<?php
namespace App\Http\Controllers;
use App\Models\SourceRecord;
use App\Services\{PublicContent,Publishing\PlatformText};
use Illuminate\Http\Request;
class PublishingPreviewController extends Controller
{
    public function __invoke(Request $request,PublicContent $content)
    {
        $data=$request->validate(['record_id'=>'required|integer|exists:source_records,id']);$record=SourceRecord::findOrFail($data['record_id']);
        abort_unless(in_array($record->kind,['video','short','post'])&&!($record->metadata['archive_data']??false),422);
        $resolver=app(\App\Services\Publishing\MediaResolver::class);$video=$resolver->video($record);$audio=$resolver->audio($record);$previews=[];
        foreach(['website','youtube','facebook','instagram','telegram','x'] as $provider){
            $title=PlatformText::value($record,$provider,'title');$body=PlatformText::value($record,$provider,'body');$caption=$provider==='youtube'?$body:PlatformText::caption($record,$provider);
            $image=$resolver->image($record,$provider);$selected=$video??$image;$render=false;$requiresWebsite=false;$buttons=null;
            if($provider==='x'){$caption=PlatformText::xCaption($record);$render=!$video&&!!$audio;if($render)$selected=null;}
            if($provider==='youtube'&&!$video){$render=!!$audio||$record->kind==='post'||($record->metadata['public_section']??null)==='podcast';if($render)$selected=null;}
            if($provider==='instagram'||$provider==='facebook'){if(!$video&&$audio){$render=true;$selected=null;}}
            if($provider==='telegram'){
                if(in_array($record->kind,['video','short'],true)||$video){$announcement=app(\App\Services\Publishing\TelegramWebsite::class)->announcement($record,true);$caption=$announcement['text'];$buttons=$announcement['reply_markup'];$selected=$image;$requiresWebsite=!$content->visible($record);}
                else $selected=$audio??$image;
            }
            $media=static fn($asset)=>$asset?['id'=>$asset['media']->id,'kind'=>$asset['media']->kind,'mime'=>$asset['media']->mime,'bytes'=>$asset['media']->bytes,'preview_url'=>route('content.preview-media',[$record,$asset['media']])]:null;
            $schedules=\App\Models\PublicationSchedule::where('source_record_id',$record->id)->whereIn('status',['scheduled','queued'])->get(['id','providers','publish_at'])->filter(fn($row)=>in_array($provider,$row->providers,true))->map(fn($row)=>['id'=>$row->id,'publish_at'=>$row->publish_at])->values();
            $previews[$provider]=['title'=>$title,'body'=>$body,'caption'=>$caption,'tags'=>$provider==='youtube'?($record->metadata['tags']??$record->metadata['original_tags']??[]):[],'visibility'=>'public','media'=>$media($selected),'cover'=>$media($image),'requires_render'=>$render,'requires_website'=>$requiresWebsite,'reply_markup'=>$buttons,'schedules'=>$schedules];
        }
        return response()->json(['id'=>$record->id,'previews'=>$previews,'website_url'=>$content->card($record)['url'],'preview_url'=>route('content.preview',$record)])->header('Cache-Control','private, no-store');
    }
}
