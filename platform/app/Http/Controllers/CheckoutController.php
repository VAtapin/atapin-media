<?php
namespace App\Http\Controllers;
use App\Models\{Product,Media};
use App\Services\StripePayments;
use Illuminate\Http\Request;
class CheckoutController extends Controller
{
    public function store(Request $request,Product $product,StripePayments $payments){return redirect()->away($payments->checkout($product,$request->user()),303);}
    public function webhook(Request $request,StripePayments $payments){$payments->webhook($request->getContent(),(string)$request->header('Stripe-Signature'));return response()->json(['received'=>true]);}
    public function download(Request $request,Product $product,StripePayments $payments)
    {
        abort_unless($payments->entitled($product,$request->user()),403);
        $media=$payments->edition($product);abort_unless($media,404);
        return app(MediaController::class)->download($media);
    }
}
