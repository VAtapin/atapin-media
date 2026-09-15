<?php

namespace Tests\Feature;

use App\Models\{Media, Product, Role, User};
use App\Services\{Access, BookCatalog, Settings};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage, URL};
use Tests\TestCase;

class StripeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(Access::class)->seed();
        $owner=User::factory()->create();
        $owner->roles()->attach(Role::where('name','Owner')->firstOrFail());
        $this->actingAs($owner);
    }

    public function test_stripe_configuration_is_validated_encrypted_and_never_echoed(): void
    {
        $this->putJson('/desktop/settings',['section'=>'integrations','provider'=>'stripe','mode'=>'test',
            'publishable_key'=>'pk_test_fixture_public','api_key'=>'sk_test_fixture_secret'])
            ->assertOk()->assertJsonPath('stripe.status','webhook_missing');
        $response=$this->putJson('/desktop/settings',['section'=>'integrations','provider'=>'stripe','mode'=>'test','webhook_secret'=>'whsec_fixture_signing'])
            ->assertOk()->assertJsonPath('stripe.status','configured')->assertJsonPath('stripe.api_key_saved',true);
        $this->assertArrayNotHasKey('api_key',$response->json('stripe'));
        $stored=DB::table('settings')->where('key','secret.integrations_stripe')->value('value');
        $this->assertIsString($stored);$this->assertStringNotContainsString('fixture_secret',$stored);$this->assertStringNotContainsString('fixture_signing',$stored);

        $this->putJson('/desktop/settings',['section'=>'integrations','provider'=>'stripe','mode'=>'test','publishable_key'=>'','api_key'=>'','webhook_secret'=>''])
            ->assertOk()->assertJsonPath('stripe.status','configured');
        $credentials=json_decode(app(Settings::class)->secret('integrations_stripe'),true);
        $this->assertSame('pk_test_fixture_public',$credentials['publishable_key']);$this->assertSame('sk_test_fixture_secret',$credentials['api_key']);$this->assertSame('whsec_fixture_signing',$credentials['webhook_secret']);

        $this->putJson('/desktop/settings',['section'=>'integrations','provider'=>'stripe','mode'=>'live'])->assertUnprocessable()->assertJsonValidationErrors('publishable_key');
        $this->putJson('/desktop/settings',['section'=>'integrations','provider'=>'stripe','mode'=>'test','api_key'=>'not-a-stripe-key'])->assertUnprocessable()->assertJsonValidationErrors('api_key');
        $this->get('/desktop')->assertOk()->assertSee(route('payments.stripe-webhook'),false)->assertSee(__('stripe.status_configured'))
            ->assertSee('data-stripe-test',false)->assertDontSee('sk_test_fixture_secret')->assertDontSee('whsec_fixture_signing');
    }

    public function test_connection_test_is_read_only_and_records_safe_status(): void
    {
        $this->putJson('/desktop/settings',['section'=>'integrations','provider'=>'stripe','mode'=>'test',
            'publishable_key'=>'pk_test_fixture_public','api_key'=>'sk_test_fixture_secret','webhook_secret'=>'whsec_fixture_signing'])->assertOk();
        $success=new class implements \Stripe\HttpClient\ClientInterface {
            public array $calls=[];
            public function request($method,$url,$headers,$params,$file,$mode='v1',$retries=null){$this->calls[]=[$method,$url];return [json_encode(['id'=>'acct_fixture','object'=>'account']),200,[]];}
        };
        \Stripe\ApiRequestor::setHttpClient($success);
        try{$this->postJson('/desktop/settings/integrations/stripe/test')->assertOk()->assertJsonPath('stripe.status','connected');}
        finally{\Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());}
        $this->assertSame([['get','https://api.stripe.com/v1/account']],$success->calls);

        $failure=new class implements \Stripe\HttpClient\ClientInterface {
            public function request($method,$url,$headers,$params,$file,$mode='v1',$retries=null){return [json_encode(['error'=>['message'=>'provider detail must stay private','type'=>'invalid_request_error']]),401,[]];}
        };
        \Stripe\ApiRequestor::setHttpClient($failure);
        try{$this->postJson('/desktop/settings/integrations/stripe/test')->assertUnprocessable()->assertJsonValidationErrors('stripe')->assertJsonPath('stripe.status','error')->assertDontSee('provider detail must stay private');}
        finally{\Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());}
        $this->assertSame('error',app(Settings::class)->get('integration_connections')['stripe']['status']);
        $this->actingAs(User::factory()->create())->postJson('/desktop/settings/integrations/stripe/test')->assertForbidden();
    }

    public function test_checkout_uses_existing_book_details_cover_and_safe_metadata(): void
    {
        app(Settings::class)->updateSecrets(['integrations_stripe'=>json_encode(['api_key'=>'sk_test_fixture_secret','webhook_secret'=>'whsec_fixture_signing'])]);
        Storage::fake('local');Storage::disk('local')->put('edition.pdf','%PDF test');Storage::disk('local')->put('cover.jpg','image');
        $product=Product::create(['title'=>'Existing book','description'=>'Existing catalogue description','isbn'=>'978-1-2345','price_cents'=>1299,'currency'=>'EUR','status'=>'active']);
        $pdf=Media::create(['title'=>'Edition','original_name'=>'edition.pdf','mime'=>'application/pdf','kind'=>'pdf','bytes'=>9,'disk'=>'local','path'=>'edition.pdf','sha256'=>hash('sha256','%PDF test'),'status'=>'ready']);
        $cover=Media::create(['title'=>'Cover','original_name'=>'cover.jpg','mime'=>'image/jpeg','kind'=>'image','bytes'=>5,'disk'=>'local','path'=>'cover.jpg','sha256'=>hash('sha256','image'),'status'=>'ready']);
        app(BookCatalog::class)->attach($product,$pdf,'full');app(BookCatalog::class)->attach($product,$cover,'cover');
        $transport=new class implements \Stripe\HttpClient\ClientInterface {
            public array $calls=[];
            public function request($method,$url,$headers,$params,$file,$mode='v1',$retries=null){$this->calls[]=[$method,$url,$params];return [json_encode(['id'=>'cs_details_fixture','object'=>'checkout.session','url'=>'https://checkout.stripe.com/c/pay/cs_details_fixture']),200,[]];}
        };
        $originalUrl=config('app.url');config(['app.url'=>'https://example.test']);URL::forceRootUrl('https://example.test');URL::forceScheme('https');
        \Stripe\ApiRequestor::setHttpClient($transport);
        try{$this->post('/buecher/'.$product->id.'/checkout')->assertRedirect('https://checkout.stripe.com/c/pay/cs_details_fixture');}
        finally{\Stripe\ApiRequestor::setHttpClient(\Stripe\HttpClient\CurlClient::instance());URL::forceRootUrl(null);URL::forceScheme(null);config(['app.url'=>$originalUrl]);}
        $params=$transport->calls[0][2];$data=$params['line_items'][0]['price_data']['product_data'];
        $this->assertSame('Existing catalogue description',$data['description']);$this->assertSame('978-1-2345',$data['metadata']['sku']);
        $this->assertSame('book',$data['metadata']['product_type']);$this->assertStringStartsWith('https://example.test/',$data['images'][0]);
        $this->assertSame((string)$product->id,$params['metadata']['product_id']);$this->assertSame((string)$product->id,$params['metadata']['book_id']);
        $this->assertSame('book',$params['metadata']['product_type']);$this->assertSame((string)auth()->id(),$params['metadata']['customer_id']);
    }
}
