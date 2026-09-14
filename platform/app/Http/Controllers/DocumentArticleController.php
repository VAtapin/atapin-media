<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Services\DocumentArticleImport;
use App\Jobs\ImportDocumentArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DocumentArticleController extends Controller
{
    public function store(Request $request,Media $media)
    {
        abort_unless(in_array($media->mime,DocumentArticleImport::MIMES,true)&&!$media->archived_at,422);
        DB::transaction(function()use($media,$request){$media=Media::lockForUpdate()->findOrFail($media->id);abort_if(($media->metadata['document_import']['status']??null)==='queued',409);$media->update(['metadata'=>[...($media->metadata??[]),'document_import'=>['status'=>'queued']]]);ImportDocumentArticle::dispatch($media->id,$request->user()->id)->afterCommit();});
        return response()->json(['status'=>'queued'],202);
    }
}
