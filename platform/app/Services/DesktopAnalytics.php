<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DesktopAnalytics
{
    public function track(Request $request,string $event,string $subject): void
    {
        if(!$request->hasSession())return;
        DB::table('analytics_events')->insertOrIgnore(['event'=>$event,'subject'=>mb_substr($subject,0,255),'visitor_hash'=>hash_hmac('sha256',$request->session()->getId(),config('app.key')),'occurred_on'=>now()->toDateString(),'created_at'=>now(),'updated_at'=>now()]);
    }
    public function report(string $start,string $end): array
    {
        $events=DB::table('analytics_events')->whereBetween('occurred_on',[$start,$end]);
        $views=DB::table('public_content_views')->whereBetween('viewed_on',[$start,$end]);
        return ['start'=>$start,'end'=>$end,'events'=>(clone $events)->select('event')->selectRaw('COUNT(*) AS count')->groupBy('event')->get(),
            'daily'=>(clone $events)->select('occurred_on')->selectRaw('COUNT(*) AS count')->groupBy('occurred_on')->orderBy('occurred_on')->get(),
            'pages'=>(clone $events)->where('event','page_view')->select('subject')->selectRaw('COUNT(*) AS count')->groupBy('subject')->orderByDesc('count')->limit(30)->get(),
            'content'=>(clone $views)->join('source_records','record_id','=','source_records.id')->whereNull('source_records.deleted_at')->select('record_id','title')->selectRaw('COUNT(*) AS count')->groupBy('record_id','title')->orderByDesc('count')->limit(30)->get(),
            'summary'=>['playbacks'=>$views->count(),'confirmed_subscribers'=>DB::table('newsletter_subscriptions')->where('status','active')->whereNotNull('confirmed_at')->count(),
                'registrations'=>DB::table('users')->whereBetween('created_at',[$start.' 00:00:00',$end.' 23:59:59'])->count(),'sales'=>DB::table('sales')->where('status','paid')->whereBetween('paid_at',[$start.' 00:00:00',$end.' 23:59:59'])->select('currency')->selectRaw('SUM(amount_cents) AS cents, COUNT(*) AS count')->groupBy('currency')->get()],
            'external_available'=>false];
    }
}
