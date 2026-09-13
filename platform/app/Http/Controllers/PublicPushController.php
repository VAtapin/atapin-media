<?php
namespace App\Http\Controllers;
use App\Models\{SourceRecord,PublicPushSubscription};
use App\Services\{PublicPush,PublicContent,PublicLiveReminders};
use Illuminate\Http\Request;
class PublicPushController extends Controller
{
    public function toggle(Request $request,SourceRecord $record,PublicPush $push,PublicContent $content)
    {
        abort_unless($content->forSection('live')->whereKey($record->id)->exists(),404);
        $data=$request->validate(['mode'=>'nullable|in:status,toggle','endpoint'=>'required|url:https|max:2000','keys.p256dh'=>'required|string|regex:/^[A-Za-z0-9_-]{87}$/','keys.auth'=>'required|string|regex:/^[A-Za-z0-9_-]{22}$/']);
        abort_unless($push->validEndpoint($data['endpoint']),422);
        $query=PublicPushSubscription::where('user_id',$request->user()->id)->where('record_id',$record->id)->where('endpoint_hash',hash('sha256',$data['endpoint']));
        if(($data['mode']??null)==='status')return response()->json(['enabled'=>$query->exists()]);
        if($query->exists()){$query->delete();return response()->json(['enabled'=>false]);}
        abort_unless($push->ready()&&app(PublicLiveReminders::class)->time($record)?->isFuture(),422,__('public.push_unavailable'));
        abort_if(PublicPushSubscription::where('user_id',$request->user()->id)->count()>=100,422);
        PublicPushSubscription::create(['user_id'=>$request->user()->id,'record_id'=>$record->id,'endpoint_hash'=>hash('sha256',$data['endpoint']),'subscription'=>['endpoint'=>$data['endpoint'],'keys'=>$data['keys']]]);
        return response()->json(['enabled'=>true]);
    }
}
