<?php
namespace App\Http\Controllers;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
class PublicContactController extends Controller
{
    public function store(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255',
            'subject'=>'required|string|max:200','body'=>'required|string|min:10|max:10000','privacy'=>'required|accepted','website'=>'nullable|string|max:0']);
        $message=ContactMessage::create([...array_intersect_key($data,array_flip(['name','email','subject','body'])),'consented_at'=>now()]);
        \App\Jobs\ForwardContactMessage::dispatch($message->id)->afterCommit();
        $status=__('public.contact_received');
        return $request->expectsJson()?response()->json(['message'=>$status]):redirect()->route('public.kontakt')->with('public_status',$status);
    }
    public function inbox(Request $request)
    {
        $request->validate(['page'=>'nullable|integer|min:1|max:100000']);
        $entries=ContactMessage::latest('id')->paginate(20);
        foreach($entries as $entry){$entry->setAttribute('delivery_label',__('public.contact_delivery_'.$entry->delivery_status));$entry->setAttribute('retry_url',route('contact.retry',$entry));}
        return response()->json($entries);
    }
    public function retry(ContactMessage $message)
    {
        if(!$message->delivered_at){
            $message->update(['delivery_status'=>'pending']);
            \App\Jobs\ForwardContactMessage::dispatch($message->id)->afterCommit();
        }
        return redirect('/desktop')->with('public_status',__('public.contact_retry_queued'));
    }
}
