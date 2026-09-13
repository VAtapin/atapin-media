<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
class PublicPasswordController extends Controller {
    public function form(Request $request,?string $token=null){return view('public.password',['title'=>__('public.password_forgot'),'section'=>'start','siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),'description'=>'','token'=>$token,'email'=>is_string($request->query('email'))?$request->query('email'):'']);}
    public function send(Request $request){$data=$request->validate(['email'=>'required|email|max:255']);Password::sendResetLink(['email'=>mb_strtolower(trim($data['email']))]);return back()->with('public_status',__('public.password_reset_requested'));}
    public function reset(Request $request){
        $data=$request->validate(['email'=>'required|email|max:255','token'=>'required|string|max:255','password'=>'required|string|min:12|max:72|confirmed']);$data['email']=mb_strtolower(trim($data['email']));
        $status=Password::reset([...$data,'password_confirmation'=>$request->input('password_confirmation')],function($user,$password){$user->forceFill(['password'=>$password,'remember_token'=>Str::random(60)])->save();event(new \Illuminate\Auth\Events\PasswordReset($user));});
        if($status!==Password::PASSWORD_RESET)return back()->withErrors(['email'=>__('public.password_reset_invalid')]);
        return redirect('/login')->with('public_status',__('public.password_reset_done'));
    }
}
