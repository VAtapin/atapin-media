<?php
namespace Tests\Feature;
use App\Models\{Media,NewsletterSubscription,User,Role,ImportRun};
use App\Services\Access;
use App\Services\Importing\ImportCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue,Mail,Http};
use Tests\TestCase;
class SubscriberCsvImportTest extends TestCase
{
    use RefreshDatabase;
    public function test_unverified_contacts_are_not_activated_emailed_or_reopted_in():void
    {
        app(Access::class)->seed();Queue::fake();Mail::fake();Http::preventStrayRequests();Storage::fake('local');$owner=User::factory()->create();$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($owner);$csv="Name;Email\nPerson;new@example.test\nPerson;optout@example.test\nDuplicate;new@example.test\nInvalid;wrong-address\n";Storage::disk('local')->put('legacy.csv',$csv);$media=Media::create(['title'=>'CSV','kind'=>'document','mime'=>'text/csv','disk'=>'local','path'=>'legacy.csv','original_name'=>'legacy.csv','sha256'=>hash('sha256',$csv),'bytes'=>strlen($csv),'status'=>'ready']);$optout=NewsletterSubscription::create(['email'=>'optout@example.test','status'=>'unsubscribed','locale'=>'de','token_hash'=>hash('sha256','old'),'consented_at'=>now(),'confirmed_at'=>now()]);$data=['media_id'=>$media->id,'delimiter'=>'semicolon','email_column'=>1,'locale'=>'de'];
        $this->postJson('/desktop/subscribers/csv/inspect',$data)->assertOk()->assertJsonPath('columns.1','Email')->assertJsonPath('rows',4)->assertJsonMissing(['email'=>'new@example.test']);$this->postJson('/desktop/subscribers/csv',$data)->assertUnprocessable();$id=$this->postJson('/desktop/subscribers/csv',[...$data,'confirm'=>true])->assertAccepted()->json('id');app(ImportCenter::class)->run(ImportRun::findOrFail($id));$this->getJson('/desktop/subscribers/csv/'.$id)->assertOk()->assertJsonPath('summary.added',1)->assertJsonPath('summary.duplicate',2)->assertJsonPath('summary.failed',1);
        $new=NewsletterSubscription::where('email','new@example.test')->firstOrFail();$this->assertSame('imported',$new->status);$this->assertNull($new->consented_at);$this->assertNull($new->confirmed_at);$this->assertSame('unsubscribed',$optout->fresh()->status);$this->assertSame($media->id,$new->import_provenance['media_id']);$this->assertSame(0,NewsletterSubscription::where('status','active')->count());Mail::assertNothingSent();Mail::assertNothingQueued();Storage::disk('local')->assertExists('legacy.csv');$this->getJson('/desktop/subscribers?status=imported')->assertJsonPath('total',1);
        $this->actingAs(User::factory()->create());$this->postJson('/desktop/subscribers/csv',[...$data,'confirm'=>true])->assertForbidden();
    }
}
