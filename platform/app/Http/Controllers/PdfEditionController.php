<?php
namespace App\Http\Controllers;
use App\Models\{Product,SourceRecord};
use App\Services\PdfEditions;
use Illuminate\Http\Request;
class PdfEditionController extends Controller
{
    public function book(Request $request,Product $product,PdfEditions $pdf){$pdf->queue($product,$request->user()->id);return response()->json(['status'=>'queued'],202);}
    public function record(Request $request,SourceRecord $record,PdfEditions $pdf){abort_unless($record->kind==='post',422);if($record->metadata['public_published']??false)\Illuminate\Support\Facades\Gate::authorize('content.publish');$pdf->queue($record,$request->user()->id);return response()->json(['status'=>'queued'],202);}
}
