<?php
namespace App\Services;
use App\Models\{Product,Sale,User,Media};
use Illuminate\Support\Facades\DB;

class StripePayments
{
    private function credentials(): array { return json_decode(app(Settings::class)->secret('integrations_stripe')??'{}',true)?:[]; }
    public function ready(): bool { $c=$this->credentials();return !empty($c['api_key'])&&!empty($c['webhook_secret']); }
    public function edition(Product $product): ?Media
    {
        return Media::where('mime','application/pdf')->whereNull('archived_at')->where('disk','!=',CanonicalMediaStorage::DISK)->whereHas('usages',fn($q)=>$q->where('subject_type',Product::class)->where('subject_id',(string)$product->id)->whereIn('used_as',['paid_download','public_download']))->get()->first(fn($media)=>app(MediaOriginalLocator::class)->find($media));
    }
    public function checkout(Product $product,User $user): string
    {
        abort_unless($this->ready(),422,__('workspaces.payment_not_ready'));
        abort_unless($product->status==='active'&&$product->price_cents>0,404);
        abort_unless($this->edition($product),422,__('workspaces.pdf_missing'));
        $sale=DB::transaction(function()use($product,$user){
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($this->entitled($product,$user),409,__('workspaces.already_owned'));
            $pending=Sale::where('user_id',$user->id)->where('product_id',$product->id)->where('status','pending')->where('created_at','>',now()->subMinutes(25))->where('amount_cents',$product->price_cents)->where('currency',$product->currency)->first();
            return $pending??Sale::create(['user_id'=>$user->id,'product_id'=>$product->id,'product_title'=>$product->title,'amount_cents'=>$product->price_cents,'currency'=>$product->currency,'customer_email'=>$user->email,'status'=>'pending','provider'=>'stripe']);
        });
        if($sale->checkout_url)return $sale->checkout_url;
        try{
            $client=new \Stripe\StripeClient($this->credentials()['api_key']);
            $session=$client->checkout->sessions->create(['mode'=>'payment','customer_email'=>$user->email,'client_reference_id'=>(string)$sale->id,'metadata'=>['sale_id'=>(string)$sale->id],
                'line_items'=>[['price_data'=>['currency'=>strtolower($sale->currency),'unit_amount'=>$sale->amount_cents,'product_data'=>['name'=>$sale->product_title]],'quantity'=>1]],
                'success_url'=>route('public.account',['payment'=>'processing']),'cancel_url'=>route('public.book',['slug'=>\Illuminate\Support\Str::slug($product->title).'-'.$product->id])],['idempotency_key'=>'sale-'.$sale->id]);
            $url=(string)$session->url;abort_unless(parse_url($url,PHP_URL_SCHEME)==='https'&&parse_url($url,PHP_URL_HOST)==='checkout.stripe.com',502);
            $sale->update(['provider_reference'=>$session->id,'checkout_url'=>$url]);return $url;
        }catch(\Throwable){abort(502,__('workspaces.payment_failed'));}
    }
    public function entitled(Product $product,User $user): bool
    {
        return DB::table('product_entitlements')->where('user_id',$user->id)->where('product_id',$product->id)->whereNull('revoked_at')->exists();
    }
    public function webhook(string $payload,string $signature): void
    {
        abort_unless($this->ready()&&strlen($payload)<=262144,400);
        try{$event=\Stripe\Webhook::constructEvent($payload,$signature,$this->credentials()['webhook_secret'],300);}catch(\Throwable){abort(400);}
        $object=$event->data->object;
        if(in_array($event->type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true)){
            if($object->payment_status!=='paid')return;
            DB::transaction(function()use($object){
                $sale=Sale::where('provider','stripe')->whereKey((int)($object->metadata->sale_id??0))->lockForUpdate()->first();
                if(!$sale||$sale->status==='refunded')return;
                abort_unless($sale->provider_reference===$object->id&&(int)$object->amount_total===$sale->amount_cents&&strtoupper((string)$object->currency)===$sale->currency,400);
                if($sale->status==='paid')return;
                $sale->update(['status'=>'paid','paid_at'=>now(),'provider_payment_id'=>$object->payment_intent]);
                DB::table('product_entitlements')->updateOrInsert(['user_id'=>$sale->user_id,'product_id'=>$sale->product_id],['sale_id'=>$sale->id,'revoked_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
                app(Audit::class)->record('shop.payment_confirmed',(string)$sale->id);
            });
        }elseif(in_array($event->type,['checkout.session.expired','checkout.session.async_payment_failed'],true)){
            Sale::where('provider','stripe')->whereKey((int)($object->metadata->sale_id??0))->where('provider_reference',$object->id)->where('status','pending')->update(['status'=>'failed']);
        }elseif($event->type==='charge.refunded' && (int)$object->amount_refunded >= (int)$object->amount){
            DB::transaction(function()use($object){$sale=Sale::where('provider','stripe')->where('provider_payment_id',$object->payment_intent)->lockForUpdate()->first();if(!$sale||$sale->status!=='paid')return;$sale->update(['status'=>'refunded']);DB::table('product_entitlements')->where('sale_id',$sale->id)->update(['revoked_at'=>now(),'updated_at'=>now()]);app(Audit::class)->record('shop.refunded',(string)$sale->id);});
        }
    }
}
