<?php
namespace App\Http\Controllers;
use App\Models\ImportRun;
use App\Jobs\ImportArchive;
use App\Services\Audit;
use Illuminate\Http\Request;
class ImportController extends Controller
{
    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(['source'=>'required|in:intake,youtube']);
        $run = ImportRun::create($data + ['user_id'=>$request->user()->id]);
        ImportArchive::dispatch($run->id);
        $audit->record('import.queued', $run->id, ['source'=>$run->source]);
        if ($request->expectsJson()) return response()->json(['status'=>'queued','import_id'=>$run->id]);
        return back()->with('status', __('ui.import_queued'));
    }
}