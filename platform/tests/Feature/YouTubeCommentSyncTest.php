<?php
namespace Tests\Feature;
use App\Models\{User,Role,SourceRecord,ChannelMessageRun,ChannelMessagePage};
use App\Services\{Access,Settings};
use App\Services\Publishing\{YouTubeClient,YouTubeCommentSync,ConnectionStore,OAuthAppCredentials};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue,Http};
use Tests\TestCase;
class YouTubeCommentSyncTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private const CHANNEL='UCabcdefghijklmnopqrstuv';
    private const VIDEO='abcdefghijk';
    protected function setUp():void{parent::setUp();app(Access::class)->seed();Queue::fake();Http::preventStrayRequests();$this->owner=User::factory()->create();$this->owner->roles()->attach(Role::where('name','Owner')->firstOrFail());$this->actingAs($this->owner);}
    private function client():\Mockery\MockInterface{$client=\Mockery::mock(YouTubeClient::class);$client->shouldReceive('configured')->andReturn(true);$client->shouldReceive('channel')->andReturn(['id'=>self::CHANNEL]);app()->instance(YouTubeClient::class,$client);return $client;}
    private function comment(string $id='root.comment',string $body='Remote comment'):array{return ['id'=>$id,'snippet'=>['textOriginal'=>$body,'authorDisplayName'=>'Author','publishedAt'=>'2026-09-01T12:00:00Z','updatedAt'=>'2026-09-01T12:00:00Z']];}
    private function thread(int $replies=0):array{return ['snippet'=>['videoId'=>self::VIDEO,'topLevelComment'=>$this->comment(),'totalReplyCount'=>$replies]];}
    public function test_full_pagination_and_replies_are_durable_unpublished_and_duplicate_safe():void
    {
        $client=$this->client();$client->shouldReceive('commentThreads')->with(self::CHANNEL,null)->once()->andReturn(['items'=>[$this->thread(1)],'nextPageToken'=>'second']);$client->shouldReceive('commentThreads')->with(self::CHANNEL,'second')->once()->andReturn(['items'=>[]]);$client->shouldReceive('commentReplies')->with('root.comment',null)->once()->andReturn(['items'=>[$this->comment('root.comment.reply','Reply')]]);
        $sync=app(YouTubeCommentSync::class);$id=$this->postJson('/desktop/community/sync',['confirm'=>true])->assertAccepted()->json('id');$run=ChannelMessageRun::findOrFail($id);$first=$run->pages()->firstOrFail();$sync->page($first->id);$this->assertSame(2,$run->fresh()->pending_pages);$sync->page($first->id);
        foreach($run->pages()->where('status','queued')->get() as $page)$sync->page($page->id);
        $this->assertSame('completed',$run->fresh()->status);$this->assertSame(0,$run->fresh()->pending_pages);$this->assertSame(3,$run->fresh()->pages);$this->assertSame(2,SourceRecord::where('kind','comment')->count());$reply=SourceRecord::where('body','Reply')->firstOrFail();$this->assertSame('root.comment',$reply->metadata['parent_comment_id']);$this->assertFalse($reply->metadata['public_published']);
        $this->getJson('/desktop/community/inbox')->assertOk()->assertJsonPath('total',2);$this->getJson('/desktop/community/sync')->assertOk()->assertJsonMissing(['page_token'=>'second']);
    }
    public function test_changed_remote_text_preserves_manual_edits_read_and_reply_state():void
    {
        $client=$this->client();$client->shouldReceive('commentThreads')->andReturn(['items'=>[$this->thread()]]);$sync=app(YouTubeCommentSync::class);
        $run=$sync->start($this->owner);$sync->page($run->pages()->first()->id);$record=SourceRecord::where('kind','comment')->firstOrFail();$record->update(['body'=>'Manual edit','metadata'=>[...$record->metadata,'inbox_read_at'=>now()->toIso8601String(),'inbox_reply'=>['status'=>'sent','external_id'=>'reply-id']]]);
        $run=$sync->start($this->owner);$sync->page($run->pages()->first()->id);$this->assertSame('Manual edit',$record->fresh()->body);$this->assertNotNull($record->fresh()->metadata['inbox_read_at']);$this->assertSame('sent',$record->fresh()->metadata['inbox_reply']['status']);
        $record->delete();$run=$sync->start($this->owner);$sync->page($run->pages()->first()->id);$this->assertTrue($record->fresh()->trashed());$this->assertSame(1,SourceRecord::withTrashed()->where('kind','comment')->count());
    }
    public function test_failed_page_retains_checkpoint_and_retry_resumes_unconsumed_pages():void
    {
        $client=$this->client();$client->shouldReceive('commentThreads')->with(self::CHANNEL,null)->andReturn(['items'=>[$this->thread(1)],'nextPageToken'=>'second']);$client->shouldReceive('commentReplies')->once()->andThrow(new \RuntimeException('secret-token'));$client->shouldReceive('commentThreads')->with(self::CHANNEL,'second')->andReturn(['items'=>[]]);
        $sync=app(YouTubeCommentSync::class);$run=$sync->start($this->owner);$sync->page($run->pages()->first()->id);$reply=$run->pages()->whereNotNull('parent_id')->first();try{$sync->page($reply->id);$this->fail('Expected failed page.');}catch(\RuntimeException $error){$this->assertStringNotContainsString('secret-token',$error->getMessage());}
        $next=$run->pages()->where('page_token','second')->first();$sync->page($next->id);$this->assertSame('queued',$next->fresh()->status);$this->assertSame('failed',$run->fresh()->status);
        $this->postJson('/desktop/community/sync/'.$run->id.'/retry')->assertAccepted();$client->shouldReceive('commentReplies')->andReturn(['items'=>[]]);$sync->page($reply->id);$sync->page($next->id);$this->assertSame('completed',$run->fresh()->status);$this->assertSame(0,$run->fresh()->pending_pages);
    }
    public function test_confirmation_permissions_and_revoked_worker_permission():void
    {
        $this->client();$this->postJson('/desktop/community/sync')->assertUnprocessable();$run=app(YouTubeCommentSync::class)->start($this->owner);$this->postJson('/desktop/community/sync',['confirm'=>true])->assertConflict();$this->owner->roles()->detach();try{app(YouTubeCommentSync::class)->page($run->pages()->first()->id);}catch(\RuntimeException){}$this->assertSame('failed',$run->fresh()->status);$this->actingAs(User::factory()->create());$this->getJson('/desktop/community/sync')->assertForbidden();$this->postJson('/desktop/community/sync',['confirm'=>true])->assertForbidden();
    }
    public function test_adapter_uses_real_authorized_plaintext_paginated_endpoints():void
    {
        app(Settings::class)->update(['social_connections'=>['youtube'=>['external_id'=>self::CHANNEL]]]);app(ConnectionStore::class)->saveCredentials('youtube',['access_token'=>'test-not-real']);app(OAuthAppCredentials::class)->save('youtube',['client_id'=>'test','client_secret'=>'test']);Http::fake(['*/commentThreads*'=>Http::response(['items'=>[]]),'*/comments*'=>Http::response(['items'=>[]])]);$client=app(YouTubeClient::class);$client->commentThreads(self::CHANNEL,'next');$client->commentReplies('root.comment','replies');Http::assertSent(fn($r)=>str_contains($r->url(),'commentThreads')&&$r['allThreadsRelatedToChannelId']===self::CHANNEL&&$r['pageToken']==='next'&&$r['textFormat']==='plainText'&&$r['maxResults']===100);Http::assertSent(fn($r)=>!str_contains($r->url(),'commentThreads')&&$r['parentId']==='root.comment'&&$r['pageToken']==='replies');
    }
}
