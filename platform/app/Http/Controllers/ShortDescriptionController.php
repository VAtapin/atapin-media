<?php
namespace App\Http\Controllers;

use App\Models\SourceRecord;
use App\Services\ContentShortDescriptions;
use App\Jobs\GenerateShortDescriptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShortDescriptionController extends Controller
{
    public function store(Request $request,ContentShortDescriptions $service)
    {
        abort_unless($service->available(),422,__('imports.ai_unavailable'));
        $data=$request->validate(['ids'=>'sometimes|array|min:1|max:1000','ids.*'=>'integer|distinct','missing'=>'sometimes|boolean']);
        abort_unless(!empty($data['ids']) || ($data['missing']??false),422);
        $records=SourceRecord::where('source','!=','catalog-reset')->whereIn('kind',['video','short','post'])
            ->where(fn($q)=>$q->whereNull('metadata->archive_data')->orWhere('metadata->archive_data',false));
        if(!empty($data['ids']))$records->whereIn('id',$data['ids']);
        $queued=[]; $token=(string)Str::uuid();
        DB::transaction(function()use($records,$data,$token,&$queued){
            foreach($records->lockForUpdate()->get() as $record){
                $meta=$record->metadata??[];
                if(($meta['short_description_origin']??null)==='manual' || (!empty($data['missing'])&&!empty($meta['short_description'])))continue;
                if(!empty($data['missing'])&&in_array($meta['short_description_job']['state']??null,['completed','insufficient'],true))continue;
                if(($meta['short_description_job']['state']??null)==='queued' && strtotime($meta['short_description_job']['queued_at']??'')>time()-600)continue;
                $record->update(['metadata'=>[...$meta,'short_description_job'=>['state'=>'queued','token'=>$token,'queued_at'=>now()->toIso8601String()]]]);
                $queued[]=$record;
            }
        });
        $groups=$service->groups($queued);
        foreach($groups as $group)dispatch(new GenerateShortDescriptions($group,$token));
        app(\App\Services\Audit::class)->record('content.short_descriptions.queued',null,['count'=>count($queued),'requests'=>count($groups)]);
        return response()->json(['status'=>'queued','count'=>count($queued),'requests'=>count($groups)]);
    }
}
