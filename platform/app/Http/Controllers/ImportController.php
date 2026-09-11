<?php
namespace App\Http\Controllers;
use App\Models\ImportRun;
use App\Models\SourceRecord;
use App\Models\Collection;
use App\Jobs\ImportArchive;
use App\Services\Audit;
use Illuminate\Http\Request;
class ImportController extends Controller
{
    public function index(Request $r)
    {
        $data=$r->validate(['kind'=>'nullable|in:video,short,post']);
        return view('imports.index',['runs'=>ImportRun::latest()->limit(10)->get(),
            'records'=>SourceRecord::when($data['kind']??null,fn($q,$kind)=>$q->where('kind',$kind))->latest()->paginate(30)->withQueryString(),
            'collections'=>Collection::withCount('items')->orderBy('title')->get()]);
    }
    public function store(Request $r,Audit $audit)
    {
        $data=$r->validate(['source'=>'required|in:intake,youtube']);
        $run=ImportRun::create($data+['user_id'=>$r->user()->id]);ImportArchive::dispatch($run->id);
        $audit->record('import.queued',$run->id,['source'=>$run->source]);
        return back()->with('status',__('ui.import_queued'));
    }
    public function show(SourceRecord $record){return view('imports.show',compact('record'));}
    public function collection(Collection $collection){$collection->load('items');return view('imports.collection',compact('collection'));}
}
