<?php
namespace App\Http\Controllers;
use App\Models\NewsletterSubscription;
use App\Jobs\ConfirmNewsletterSubscription;
use Illuminate\Http\Request;
class PublicNewsletterController extends Controller {
    public function store(Request $request){
        $data=$request->validate(['email'=>'required|email|max:255','consent'=>'accepted','website'=>'nullable|string|size:0']);
        $email=mb_strtolower(trim($data['email']));
        $entry=NewsletterSubscription::firstOrNew(['email'=>$email]);
        // Do not let an anonymous request revoke an existing confirmed subscription.
        if($entry->status!=='active'&&(!$entry->exists||$entry->updated_at->lt(now()->subMinutes(15)))){
            $token=bin2hex(random_bytes(32));
            $entry->fill(['locale'=>app()->getLocale(),'status'=>'pending','delivery_status'=>'pending','consented_at'=>now(),'confirmed_at'=>null,'token_hash'=>hash('sha256',$token)])->save();
            ConfirmNewsletterSubscription::dispatch($entry->id,$token)->afterCommit();
        }
        $message=__('public.newsletter_requested');
        return $request->expectsJson()?response()->json(['message'=>$message]):back()->with('public_status',$message);
    }
    private function valid(NewsletterSubscription $subscription,Request $request): void {abort_unless(is_string($request->query('token'))&&hash_equals($subscription->token_hash,hash('sha256',$request->query('token'))),404);}
    public function confirm(NewsletterSubscription $subscription,Request $request){$this->valid($subscription,$request);abort_unless($subscription->status==='pending'||$subscription->status==='active',410);$subscription->update(['status'=>'active','confirmed_at'=>$subscription->confirmed_at??now()]);return redirect('/')->with('public_status',__('public.newsletter_confirmed'));}
    public function cancel(NewsletterSubscription $subscription,Request $request){$this->valid($subscription,$request);return view('public.newsletter-cancel',['entry'=>$subscription,'title'=>__('public.newsletter_cancel'),'section'=>'start','siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),'description'=>'']);}
    public function destroy(NewsletterSubscription $subscription,Request $request){$this->valid($subscription,$request);$subscription->update(['status'=>'unsubscribed']);return redirect('/')->with('public_status',__('public.newsletter_cancelled'));}
}
