<?php
namespace App\Http\Controllers;
use App\Models\{NewsletterSubscription,NewsletterCampaign};
use App\Services\{Audit,NewsletterCampaigns};
use Illuminate\Http\Request;
class NewsletterWorkspaceController extends Controller
{
    public function subscribers(Request $request)
    {
        $data=$request->validate(['q'=>'nullable|string|max:120','status'=>'nullable|in:pending,active,unsubscribed','page'=>'nullable|integer|min:1']);
        return NewsletterSubscription::when($data['q']??'',fn($q,$s)=>$q->where('email','like','%'.$s.'%'))->when($data['status']??'',fn($q,$s)=>$q->where('status',$s))->latest()->paginate(30);
    }
    public function subscriber(Request $request,NewsletterSubscription $subscription)
    {
        $data=$request->validate(['tags'=>'required|array|max:20','tags.*'=>'string|max:100','unsubscribe'=>'sometimes|boolean']);
        $subscription->update(['tags'=>array_values(array_unique($data['tags'])),...(!empty($data['unsubscribe'])?['status'=>'unsubscribed']:[])]);app(Audit::class)->record('newsletter.subscriber_updated',(string)$subscription->id);return response()->json(['status'=>'saved']);
    }
    public function campaigns(){return NewsletterCampaign::withCount(['deliveries','deliveries as sent_count'=>fn($q)=>$q->where('status','sent'),'deliveries as failed_count'=>fn($q)=>$q->whereIn('status',['failed','unknown'])])->latest()->paginate(30);}
    public function store(Request $request){return $this->save($request);}
    public function update(Request $request,NewsletterCampaign $campaign){abort_unless($campaign->status==='draft',409);return $this->save($request,$campaign);}
    private function save(Request $request,?NewsletterCampaign $campaign=null)
    {
        $data=$request->validate(['subject'=>'required|string|max:255','body'=>'required|string|max:20000','locale'=>'nullable|in:de,en','segment'=>'nullable|string|max:100']);
        return \Illuminate\Support\Facades\DB::transaction(function()use($campaign,$data,$request){$campaign=$campaign?NewsletterCampaign::lockForUpdate()->findOrFail($campaign->id):new NewsletterCampaign(['status'=>'draft']);abort_unless($campaign->status==='draft',409);$campaign->fill([...$data,'user_id'=>$request->user()->id]);$campaign->save();app(Audit::class)->record('newsletter.campaign_saved',(string)$campaign->id);return $campaign;});
    }
    public function preview(NewsletterCampaign $campaign){return view('mail.newsletter-campaign',['campaign'=>$campaign,'cancel'=>'#']);}
    public function send(Request $request,NewsletterCampaign $campaign,NewsletterCampaigns $service)
    {
        $data=$request->validate(['confirm_send'=>'required|accepted','send_at'=>'nullable|date|after:now']);
        \Illuminate\Support\Facades\DB::transaction(function()use($campaign,$data,$request,$service){$campaign=NewsletterCampaign::lockForUpdate()->findOrFail($campaign->id);abort_unless($campaign->status==='draft',409);$campaign->update(['user_id'=>$request->user()->id]);
            if($data['send_at']??null)$campaign->update(['status'=>'scheduled','send_at'=>\Illuminate\Support\Carbon::parse($data['send_at'],app(\App\Services\Settings::class)->get('system_timezone',config('platform.timezone')))->utc()]);else $service->send($campaign);
        });
        return response()->json(['status'=>$campaign->fresh()->status],202);
    }
    public function cancel(NewsletterCampaign $campaign){abort_unless(NewsletterCampaign::whereKey($campaign->id)->whereIn('status',['scheduled','preparing','sending'])->update(['status'=>'cancelled']),409);$campaign->deliveries()->whereIn('status',['pending','queued'])->update(['status'=>'skipped']);app(Audit::class)->record('newsletter.cancelled',(string)$campaign->id);return response()->json(['status'=>'cancelled']);}
    public function export()
    {
        app(Audit::class)->record('newsletter.exported');
        return response()->streamDownload(function(){$out=fopen('php://output','w');fputcsv($out,['email','status','confirmed_at','locale','tags'],',','"','');NewsletterSubscription::orderBy('id')->chunkById(500,function($rows)use($out){foreach($rows as $r){$safe=fn($s)=>preg_match('/^[=+@\-]/',(string)$s)?"'".$s:$s;fputcsv($out,array_map($safe,[$r->email,$r->status,$r->confirmed_at?->toIso8601String(),$r->locale,implode(', ',$r->tags??[])]),',','"','');}});fclose($out);},'subscribers.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }
    public function optout(Request $request,NewsletterSubscription $subscription)
    {
        if($request->isMethod('get'))return view('public.newsletter-cancel',['title'=>__('public.newsletter_cancel'),'section'=>'','siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),'description'=>'','subscription'=>$subscription]);
        $subscription->update(['status'=>'unsubscribed']);return redirect('/')->with('public_status',__('public.saved'));
    }
}
