<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Services\Audit;
use Illuminate\Http\Request;
class ShopController extends Controller
{
    public function index(){return view('public.book-admin',['title'=>__('public.book_manage'),'section'=>'buecher','siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),'description'=>'','products'=>Product::latest()->paginate(20),'reviews'=>\App\Models\BookReview::with('user','product')->where('status','pending')->latest()->limit(50)->get()]);}
    public function update(Request $request,Product $product,Audit $audit){app(\App\Services\BookCatalog::class)->save($request->validate(\App\Services\BookCatalog::rules()),$product);$audit->record('shop.product_updated',(string)$product->id);return back()->with('public_status',__('public.saved'));}
    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(\App\Services\BookCatalog::rules());
        $product = app(\App\Services\BookCatalog::class)->save($data);
        $audit->record('shop.product_created', (string) $product->id);
        if ($request->expectsJson()) return response()->json(['status'=>'saved','product_id'=>$product->id]);
        return back()->with('status', __('ui.saved'));
    }
}
