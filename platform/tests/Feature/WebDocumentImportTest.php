<?php
namespace Tests\Feature;
use App\Models\{User,Role,SourceRecord,Media};
use App\Services\{Access,PublicDocumentDownload};
use App\Services\Importing\{WebDocumentAdapter,ImportCenter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue,Http};
use Tests\TestCase;
class WebDocumentImportTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void{parent::setUp();app(Access::class)->seed();Queue::fake();Http::preventStrayRequests();Storage::fake('local');$owner=User::factory()->create();$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($owner);}
    public function test_rss_atom_and_html_are_sanitized_without_entity_loading():void
    {
        $adapter=app(WebDocumentAdapter::class);$rss='<rss><channel><item><guid>one</guid><title>First</title><description><![CDATA[<p>Body</p><script>bad()</script>]]></description><link>https://example.com/one</link></item></channel></rss>';$rows=$adapter->parse($rss,'https://example.com/feed');$this->assertCount(1,$rows);$this->assertSame('one',$rows[0]['key']);$this->assertStringNotContainsString('script',$rows[0]['body']);
        $rows=$adapter->parse('<feed xmlns="http://www.w3.org/2005/Atom"><entry><id>atom-one</id><title>Atom</title><link href="https://example.com/atom"/><content type="html">&lt;p&gt;Content&lt;/p&gt;</content></entry></feed>','https://example.com/feed');$this->assertSame('https://example.com/atom',$rows[0]['url']);$this->assertStringContainsString('Content',$rows[0]['body']);
        $rows=$adapter->parse('<html><head><title>Article</title></head><body><nav>Menu</nav><article><h1>Article</h1><p>Body</p><script>bad()</script></article></body></html>','https://example.com/article');$this->assertStringNotContainsString('Menu',$rows[0]['body']);$this->assertStringNotContainsString('bad()',$rows[0]['body']);
        $this->expectException(\RuntimeException::class);$adapter->parse('<!DOCTYPE rss [<!ENTITY secret SYSTEM "file:///etc/passwd">]><rss><channel/></rss>','https://example.com/feed');
    }
    public function test_real_queue_adapter_retains_private_original_and_manual_edits():void
    {
        Storage::disk('local')->put('remote.html','<html><title>Imported article</title><article><p>Original body</p></article></html>');$download=\Mockery::mock(PublicDocumentDownload::class);$download->shouldReceive('download')->with('https://example.com/article')->once()->andReturn(['path'=>Storage::disk('local')->path('remote.html'),'mime'=>'text/html']);app()->instance(PublicDocumentDownload::class,$download);
        $center=app(ImportCenter::class);$run=$center->queueRun((string)auth()->id(),'web-document',['source_ref'=>'https://example.com/article']);$center->run($run);$this->assertSame('complete',$run->fresh()->status);$record=SourceRecord::where('source','web-document')->firstOrFail();$this->assertSame('review',$record->status);$this->assertFalse($record->metadata['public_published']);$media=Media::findOrFail($record->metadata['media_ids'][0]);$this->assertSame('local',$media->disk);Storage::disk('local')->assertExists($media->path);$this->assertSame(1,$media->usages()->count());
        $record->update(['body'=>'Manual body','status'=>'ready']);Storage::disk('local')->put('remote2.html','<html><title>Changed</title><article><p>Changed remote body</p></article></html>');$download->shouldReceive('download')->andReturn(['path'=>Storage::disk('local')->path('remote2.html'),'mime'=>'text/html']);$run=$center->queueRun((string)auth()->id(),'web-document',['source_ref'=>'https://example.com/article']);$center->run($run);$this->assertSame('Manual body',$record->fresh()->body);$this->assertSame(1,SourceRecord::where('source','web-document')->count());
    }
    public function test_private_addresses_and_https_downgrades_are_rejected():void
    {
        $download=new class extends PublicDocumentDownload {public function addresses(string $host):array{return ['127.0.0.1'];}};
        foreach(['https://example.com/rss','http://example.com/rss'] as $url){try{$download->target($url);$this->fail('Unsafe URL accepted.');}catch(\RuntimeException){$this->addToAssertionCount(1);}}
        $this->expectException(\Illuminate\Validation\ValidationException::class);app(WebDocumentAdapter::class)->validate(['source_ref'=>'https://user:pass@example.com/article']);
    }
}
