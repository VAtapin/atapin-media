<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Services\Importing\RecordOrganization;
class RecordOrganizationController extends Controller
{
    public function update(Request $request,RecordOrganization $organization)
    {
        $data=$request->validate(['ids'=>'required|array|min:1|max:100','ids.*'=>'required|integer|distinct',
            'status'=>'sometimes|in:unsorted,ready,needs_attention','target_profile'=>'sometimes|in:media_library,videos,shorts,posts,polls,comments',
            'add_tags'=>'sometimes|array|min:1|max:30','add_tags.*'=>'required|string|max:100']);
        return response()->json(['status'=>'saved','count'=>$organization->apply($data)]);
    }
}
