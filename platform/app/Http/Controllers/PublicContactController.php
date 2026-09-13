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
        ContactMessage::create([...array_intersect_key($data,array_flip(['name','email','subject','body'])),'consented_at'=>now()]);
        return redirect()->route('public.kontakt')->with('public_status',__('public.contact_received'));
    }
    public function inbox(Request $request)
    {
        $request->validate(['page'=>'nullable|integer|min:1|max:100000']);
        return response()->json(ContactMessage::latest('id')->paginate(20));
    }
}
