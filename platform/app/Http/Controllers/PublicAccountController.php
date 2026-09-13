<?php
namespace App\Http\Controllers;
use App\Models\{User,SourceRecord,Product,PublicContentState,PublicPushSubscription};
use App\Services\{Settings,PublicContent,PublicBooks};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class PublicAccountController extends Controller {
    private function shared(): array {return ['title'=>__('public.account'),'section'=>'start','siteName'=>app(Settings::class)->get('site_name',config('platform.brand')),'description'=>''];}
    public function registration(){return view('public.registration',$this->shared());}
    public function register(Request $request){
        if(is_string($request->input('email')))$request->merge(['email'=>mb_strtolower(trim($request->input('email')))]);
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255|unique:users,email','password'=>'required|string|min:12|max:72|confirmed','consent'=>'accepted','website'=>'nullable|string|size:0']);
        $user=User::create(['name'=>$data['name'],'email'=>mb_strtolower(trim($data['email'])),'password'=>$data['password']]);
        \App\Jobs\VerifyPublicAccount::dispatch($user->id,$user->email,app()->getLocale())->afterCommit();
        Auth::login($user);$request->session()->regenerate();return redirect('/konto')->with('public_status',__('public.account_verify'));
    }
    public function verify(Request $request,User $user,string $hash){abort_unless($request->user()->id===$user->id&&hash_equals(sha1($user->email),$hash),403);$user->forceFill(['email_verified_at'=>now()])->save();return redirect('/konto')->with('public_status',__('public.account_verified'));}
    public function resend(Request $request){$user=$request->user();if($user->isStaffAccount())return back()->with('public_status',__('public.saved'));if(!$user->email_verified_at)\App\Jobs\VerifyPublicAccount::dispatch($user->id,$user->email,app()->getLocale());return back()->with('public_status',__('public.account_verify'));}
    public function index(Request $request,PublicContent $content,PublicBooks $books){
        $isStaffAccount=$request->user()->isStaffAccount();
        if($isStaffAccount)return response()->view('public.account',[...$this->shared(),'isStaffAccount'=>true])->header('Cache-Control','private, no-store');
        $states=PublicContentState::where('user_id',$request->user()->id)->latest('updated_at')->paginate(30);
        $items=collect();foreach($states as $state){$subject=$state->subject_type==='book'?$books->query()->find($state->subject_id):$content->query()->find($state->subject_id);if(!$subject||($subject instanceof SourceRecord&&!$content->visible($subject)))continue;$card=$subject instanceof Product?$books->card($subject):$content->card($subject);$items->push(['state'=>$state,'card'=>$card]);}
        $push=PublicPushSubscription::where('user_id',$request->user()->id)->get()->map(function($entry)use($content){
            $event=SourceRecord::find($entry->record_id);
            return $event&&$content->visible($event)?['subscription'=>$entry,'card'=>$content->card($event)]:null;
        })->filter()->values();
        return response()->view('public.account',[...$this->shared(),'items'=>$items,'states'=>$states,'push'=>$push,'isStaffAccount'=>$isStaffAccount,'requiresEmailVerification'=>!$isStaffAccount&&!$request->user()->email_verified_at])->header('Cache-Control','private, no-store');
    }
    public function remove(Request $request,PublicContentState $state){abort_unless($state->user_id===$request->user()->id,404);$state->delete();return back()->with('public_status',__('public.saved'));}
    public function cancelPush(Request $request,PublicPushSubscription $subscription){abort_unless($subscription->user_id===$request->user()->id,404);$subscription->delete();return back()->with('public_status',__('public.saved'));}
    public function profile(Request $request){abort_unless(!$request->user()->isStaffAccount(),404);$data=$request->validate(['name'=>'required|string|max:120','current_password'=>'required|current_password','password'=>'nullable|string|min:12|max:72|confirmed']);$user=$request->user();$user->name=$data['name'];if(!empty($data['password']))$user->password=$data['password'];$user->save();return back()->with('public_status',__('public.saved'));}
}
