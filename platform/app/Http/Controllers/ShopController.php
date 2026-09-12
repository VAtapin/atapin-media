<?php
namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Audit;
use Illuminate\Http\Request;
class ShopController extends Controller
{
    public function index(Request $request) {
        $data = ['products'=>Product::latest()->get(), 'sales'=>Sale::with('product')->latest()->limit(20)->get()];
        return view($request->boolean('embed') ? 'shop-embed' : 'shop.index', $data);
    }
    public function store(Request $request, Audit $audit) {
        $data = $request->validate(['title'=>'required|string|max:255','description'=>'nullable|string|max:2000','price_cents'=>'nullable|integer|min:0','currency'=>'required|string|size:3','status'=>'required|in:draft,active,archived']);
        $product = Product::create($data); $audit->record('shop.product_created', (string) $product->id);
        return back()->with('status', __('ui.saved'));
    }
}