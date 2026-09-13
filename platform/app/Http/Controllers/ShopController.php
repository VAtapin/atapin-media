<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Services\Audit;
use Illuminate\Http\Request;
class ShopController extends Controller
{
    public function index(){return view('public.book-admin',['title'=>__('public.book_manage'),'section'=>'buecher','siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),'description'=>'','products'=>Product::latest()->paginate(20),'reviews'=>\App\Models\BookReview::with('user','product')->where('status','pending')->latest()->limit(50)->get()]);}
    public function update(Request $request,Product $product,Audit $audit){$data=$request->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:10000','price_cents'=>'nullable|integer|min:0|max:10000000','currency'=>'required|in:EUR,USD,CHF,GBP','status'=>'required|in:draft,active,archived','author'=>'nullable|string|max:255','contents'=>'nullable|string|max:20000','isbn'=>'nullable|string|max:32','language'=>'nullable|string|max:8','page_count'=>'nullable|integer|min:1|max:100000']);$product->update($data);$audit->record('shop.product_updated',(string)$product->id);return back()->with('public_status',__('public.saved'));}
    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:2000','price_cents'=>'nullable|integer|min:0','currency'=>'required|string|size:3','status'=>'required|in:draft,active,archived']);
        $product = Product::create($data);
        $audit->record('shop.product_created', (string) $product->id);
        if ($request->expectsJson()) return response()->json(['status'=>'saved','product_id'=>$product->id]);
        return back()->with('status', __('ui.saved'));
    }
}
