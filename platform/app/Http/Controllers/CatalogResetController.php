<?php
namespace App\Http\Controllers;
use App\Services\Importing\CatalogReset;
use App\Services\Audit;
use Illuminate\Http\Request;
class CatalogResetController extends Controller
{
    public function preview(CatalogReset $reset){return response()->json($reset->preview());}
    public function store(Request $request,CatalogReset $reset,Audit $audit)
    {
        $request->validate(['confirmation'=>'required|in:RESET']);
        $result=$reset->reset();$audit->record('catalog.reset',$result['reset_id'],$result);
        return response()->json($result);
    }
    public function restore(Request $request,CatalogReset $reset,Audit $audit)
    {
        $data=$request->validate(['reset_id'=>'required|uuid']);$reset->restore($data['reset_id']);
        $audit->record('catalog.restored',$data['reset_id']);return response()->json(['status'=>'restored']);
    }
}
