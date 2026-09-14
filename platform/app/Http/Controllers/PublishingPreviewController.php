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
        $previews=[];foreach(['website','youtube','facebook','instagram','telegram','x'] as $provider){$title=PlatformText::value($record,$provider,'title');$body=PlatformText::value($record,$provider,'body');if($provider==='telegram')$body=strip_tags(($record->metadata['platform_metadata']['telegram']['body']??null)?:($record->metadata['short_description']??''));$caption=$provider==='x'?\Illuminate\Support\Str::limit(PlatformText::caption($record,$provider),280,''):trim($title."\n\n".$body);if($provider==='telegram')$caption=\Illuminate\Support\Str::limit($caption,1024,'…');$previews[$provider]=['title'=>$title,'body'=>$body,'caption'=>$caption,'tags'=>$record->metadata['tags']??[],'visibility'=>'public'];}
        return response()->json(['id'=>$record->id,'previews'=>$previews,'website_url'=>$content->card($record)['url'],'preview_url'=>route('content.preview',$record)])->header('Cache-Control','private, no-store');
    }
}
