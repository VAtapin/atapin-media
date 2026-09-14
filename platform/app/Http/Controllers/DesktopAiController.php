<?php

namespace App\Http\Controllers;

use App\Models\DesktopAiRequest;
use App\Services\{ContentShortDescriptions, DesktopAi, Settings};
use Illuminate\Http\Request;

class DesktopAiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['page'=>'nullable|integer|min:1']);
        return response()->json(['requests'=>DesktopAiRequest::with('record:id,title')->where('user_id',$request->user()->id)->latest()->paginate(20),
            'available'=>app(ContentShortDescriptions::class)->available(),'model'=>app(Settings::class)->get('ai_model'),'daily_limit'=>50]);
    }
    public function store(Request $request, DesktopAi $service)
    {
        $data = $request->validate(['question'=>'required|string|min:2|max:4000','purpose'=>'required|in:chat,title,summary,seo,social,reply',
            'source_record_id'=>'nullable|integer|exists:source_records,id']);
        if (in_array($data['purpose'],['title','summary','seo'],true)) abort_unless(!empty($data['source_record_id']),422);
        $entry = $service->submit($request->user(),$data);
        return response()->json(['id'=>$entry->id,'status'=>'queued'],202);
    }
    public function apply(Request $request, DesktopAiRequest $entry, DesktopAi $service)
    {
        abort_unless($entry->user_id === $request->user()->id,404);
        $service->apply($entry);
        return response()->json(['status'=>'applied']);
    }
}
