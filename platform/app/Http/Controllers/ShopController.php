<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Services\Audit;
use Illuminate\Http\Request;
class ShopController extends Controller
{
    public function index(){return redirect('/desktop?open=books-pdf',303);}
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
