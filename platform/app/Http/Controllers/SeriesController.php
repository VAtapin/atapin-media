<?php
namespace App\Http\Controllers;
use App\Models\Collection;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\{Str,Facades\Gate};
class SeriesController extends Controller
{
    public function index(Request $request){$data=$request->validate(['q'=>'nullable|string|max:120','page'=>'nullable|integer|min:1']);return Collection::where('metadata->workspace_series',true)->withCount('items')->when($data['q']??'',fn($q,$s)=>$q->where('title','like','%'.$s.'%'))->latest()->paginate(30);}
    public function show(Collection $collection){abort_unless($collection->metadata['workspace_series']??false,404);return response()->json(['series'=>$collection,'items'=>$collection->items()->paginate(50)]);}
    public function store(Request $request){return $this->save($request);}
    public function update(Request $request,Collection $collection){abort_unless($collection->metadata['workspace_series']??false,404);return $this->save($request,$collection);}
    private function save(Request $request,?Collection $collection=null)
    {
        $data=$request->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:10000','section'=>'required|in:videos,podcast','public_published'=>'required|boolean']);
        if($data['public_published']||($collection->metadata['public_published']??false))Gate::authorize('content.publish');
        $collection??=new Collection(['source'=>'manual','source_id'=>(string)Str::uuid()]);
        $collection->fill(['title'=>$data['title'],'description'=>$data['description']??'','metadata'=>[...($collection->metadata??[]),'workspace_series'=>true,'public_section'=>$data['section'],'public_published'=>$data['public_published']]]);$collection->save();
        app(Audit::class)->record('series.saved',(string)$collection->id);return $collection;
    }
}
