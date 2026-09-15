<?php
namespace App\Http\Controllers;
use App\Models\{Product,BookReview};
use App\Services\PublicBooks;
use Illuminate\Http\Request;
class PublicBookReviewController extends Controller {
    public function store(Request $request,Product $product,PublicBooks $books){
        abort_unless($books->query()->whereKey($product->id)->exists(),404);
        $data=$request->validate(['rating'=>'required|integer|min:1|max:5','body'=>'required|string|min:2|max:3000']);
        BookReview::updateOrCreate(['product_id'=>$product->id,'user_id'=>$request->user()->id],[...$data,'status'=>'pending']);
        $message=__('public.comment_pending');
        return $request->expectsJson()?response()->json(['message'=>$message,'kind'=>'review']):back()->with('public_status',$message);
    }
}
