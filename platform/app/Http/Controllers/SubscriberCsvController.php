<?php
namespace App\Http\Controllers;
use App\Models\{Media,ImportRun};
use App\Services\Importing\{SubscriberCsvAdapter,ImportCenter,ImportJournal};
use Illuminate\Http\Request;
class SubscriberCsvController extends Controller
{
    public function inspect(Request $request,SubscriberCsvAdapter $adapter){$data=$request->validate(['media_id'=>'required|uuid|exists:media,id','delimiter'=>'required|in:comma,semicolon,tab']);$rows=$adapter->rows(Media::findOrFail($data['media_id']),$data['delimiter']);return response()->json(['columns'=>array_map(fn($header)=>mb_substr((string)$header,0,100),$rows[0]),'rows'=>count($rows)-1])->header('Cache-Control','private, no-store');}
    public function store(Request $request,ImportCenter $center,SubscriberCsvAdapter $adapter){$request->validate(['confirm'=>'required|accepted']);$run=$center->queueRun((string)$request->user()->id,'subscriber-csv',$adapter->validate($request->all()));return response()->json(['id'=>$run->id,'status'=>'queued'],202);}
    public function show(Request $request,ImportRun $run){abort_unless($run->source==='subscriber-csv',404);return response()->json(['id'=>$run->id,'status'=>$run->status,'summary'=>app(ImportJournal::class)->summary($run),'error'=>$run->error]);}
}
