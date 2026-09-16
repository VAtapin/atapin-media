<?php
namespace App\Http\Controllers;
use App\Models\{Product,Media,Sale};
use App\Services\{Audit,BookCatalog,MediaLibrary,StripePayments};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $data=$request->validate(['q'=>'nullable|string|max:120','status'=>'nullable|in:draft,active,archived','page'=>'nullable|integer|min:1']);
        $page = Product::with('project:id,title')->when($data['q']??'',fn($q,$s)=>$q->where('title','like','%'.$s.'%'))
            ->when($data['status']??'',fn($q,$s)=>$q->where('status',$s))->latest()->paginate(30);
        $covers = Media::whereIn('id', $page->getCollection()->pluck('metadata.cover_media_id')->filter())->get()->keyBy('id');
        $fullIds = Media::whereIn('id', \Illuminate\Support\Facades\DB::table('media_usages')->where('subject_type',Product::class)->whereIn('subject_id',$page->getCollection()->pluck('id')->map(fn($id)=>(string)$id))->whereIn('used_as',['public_download','paid_download'])->pluck('media_id'))->where('mime','application/pdf')->pluck('id');
        return response()->json($page->through(fn(Product $book)=>[...$book->toArray(),
            'cover_url'=>$covers->get($book->metadata['cover_media_id']??null)?->previewUrl(),
            'pdf_ready'=>$fullIds->isNotEmpty()]));
    }
    public function show(Product $product)
    {
        $assets=Media::whereHas('usages',fn($q)=>$q->where('subject_type',Product::class)->where('subject_id',(string)$product->id))->with(['usages'=>fn($q)=>$q->where('subject_type',Product::class)->where('subject_id',(string)$product->id)])->get();
        return response()->json(['product'=>$product,'assets'=>$assets->map(fn($m)=>['id'=>$m->id,'title'=>$m->title,'mime'=>$m->mime,'roles'=>$m->usages->pluck('used_as'),'preview_url'=>route('media.preview',$m)]),'payment_ready'=>app(StripePayments::class)->ready()]);
    }
    public function store(Request $request,BookCatalog $catalog){return response()->json($catalog->save($request->validate(BookCatalog::rules())),201);}
    public function update(Request $request,Product $product,BookCatalog $catalog){return $catalog->save($request->validate(BookCatalog::rules()),$product);}
    public function destroy(Request $request, Product $product, Audit $audit)
    {
        $request->validate(['confirmation'=>'required|in:DELETE']);
        $productId = $product->id;
        DB::transaction(function () use ($product) {
            DB::table('taxonomy_assignments')->where('subject_type', 'product')->where('subject_id', (string) $product->id)->delete();
            DB::table('media_usages')->where('subject_type', Product::class)->where('subject_id', (string) $product->id)->delete();
            DB::table('book_reviews')->where('product_id', $product->id)->delete();
            $product->delete();
        });
        $audit->record('shop.product_deleted', (string) $productId);
        return response()->json(['status'=>'deleted','id'=>$productId]);
    }
    public function intake(Request $request, BookCatalog $catalog)
    {
        Gate::authorize('media.upload');
        $data=$request->validate(['media_id'=>'required_without:file|nullable|uuid|exists:media,id','file'=>'required_without:media_id|nullable|file|mimes:pdf|max:51200','original_name'=>'nullable|string|max:255']);
        $media=$request->hasFile('file')?app(MediaLibrary::class)->upload($request->file('file'),$request->user()->id):Media::visibleLibrary()->findOrFail($data['media_id']);
        abort_unless(in_array($media->mime,['application/pdf','application/x-pdf'],true),422);
        $originalName=$request->hasFile('file')?$request->file('file')->getClientOriginalName():($data['original_name']??$media->title);
        $title = Str::limit(trim(pathinfo($originalName, PATHINFO_FILENAME)) ?: __('workspaces.new_book'), 255, '');
        $product = $catalog->save(['title'=>$title,'currency'=>'EUR','price_cents'=>0,'status'=>'draft',
            'metadata'=>['book_pdf_ai'=>['status'=>'queued','seed_title'=>$title]]]);
        $catalog->attach($product, $media, 'full');
        \App\Jobs\AnalyzeBookPdf::dispatch($product->id, $media->id, $request->user()->id)->afterCommit();
        return response()->json(['status'=>'queued','id'=>$product->id,'media_id'=>$media->id], 202);
    }
    public function analyze(Request $request, Product $product)
    {
        $state = $product->metadata['book_pdf_ai']['status'] ?? null;
        abort_if(in_array($state, ['queued','processing'], true), 409, __('workspaces.book_ai_running'));
        $media = Media::where('mime','application/pdf')->whereNull('archived_at')
            ->whereHas('usages', fn($q) => $q->where('subject_type', Product::class)->where('subject_id', (string) $product->id)->whereIn('used_as', ['full','public_download','paid_download']))
            ->latest('media.created_at')->first();
        abort_unless($media, 422, __('workspaces.book_pdf_missing'));
        $metadata = $product->metadata ?? [];
        $metadata['book_pdf_ai'] = [...($metadata['book_pdf_ai'] ?? []), 'status'=>'queued', 'seed_title'=>(string) ($metadata['book_pdf_ai']['seed_title'] ?? $product->title), 'queued_at'=>now()->toIso8601String()];
        unset($metadata['book_pdf_ai']['error']);
        $product->updateQuietly(['metadata'=>$metadata]);
        \App\Jobs\AnalyzeBookPdf::dispatch($product->id, $media->id, $request->user()->id)->afterCommit();
        return response()->json(['status'=>'queued','id'=>$product->id], 202);
    }
    public function asset(Request $request,Product $product,BookCatalog $catalog)
    {
        $data=$request->validate(['slot'=>'required|in:cover,sample,full','media_id'=>'required_without:file|nullable|uuid|exists:media,id','file'=>'required_without:media_id|nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:51200']);
        if($request->hasFile('file')) { Gate::authorize('media.upload'); $media=app(MediaLibrary::class)->upload($request->file('file'),$request->user()->id); }
        else $media=Media::findOrFail($data['media_id']);
        $catalog->attach($product,$media,$data['slot']);return response()->json(['status'=>'saved','media_id'=>$media->id]);
    }
    public function sales(Request $request)
    {
        $data=$request->validate(['status'=>'nullable|in:pending,paid,refunded,failed','page'=>'nullable|integer|min:1']);
        return response()->json(['sales'=>Sale::with('product:id,title')->when($data['status']??'',fn($q,$s)=>$q->where('status',$s))->latest()->paginate(30),
            'payment_ready'=>app(StripePayments::class)->ready(),'paid_cents'=>(int)Sale::where('status','paid')->where('currency','EUR')->sum('amount_cents')]);
    }
}
