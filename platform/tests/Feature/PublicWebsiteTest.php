<?php
namespace Tests\Feature;

use App\Models\{SourceRecord,Media,Role,User};
use App\Services\{Access,PublicContent};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;
    private function record(array $data=[]): SourceRecord
    {
        return SourceRecord::create([...['source'=>'youtube','source_id'=>'abcdefghijk','kind'=>'video','title'=>'Public teaching','body'=>'Original text','status'=>'ready','metadata'=>[]],...$data]);
    }
    public function test_home_and_navigation_are_real_pages_without_private_imports(): void
    {
        $record=$this->record(['title'=>'Private archive title']);
        $this->get('/')->assertOk()->assertSee('public-section-cards',false)->assertDontSee('Private archive title');
        foreach(['videos','beitraege','buecher','live','podcast','community','ueber-uns','kontakt','datenschutz','impressum','suche'] as $path)$this->get('/'.$path)->assertOk();
        $this->get('/videos/private-'.$record->id)->assertNotFound();
    }
    public function test_only_explicit_ready_publications_are_listed_and_can_be_revoked(): void
    {
        $record=$this->record(['metadata'=>['public_published'=>true]]);
        $url=app(PublicContent::class)->card($record)['url'];
        $this->get('/')->assertOk()->assertSee('Public teaching');
        $this->get($url)->assertOk()->assertSee('Original text');
        $this->get('/suche?q=unmatched')->assertDontSee('Public teaching');
        foreach([['public_published'=>false],['public_published'=>true,'archive_data'=>true],['public_published'=>true,'library_only'=>true]] as $metadata){
            $record->update(['metadata'=>$metadata]);$this->get($url)->assertNotFound();
        }
        $record->update(['metadata'=>['public_published'=>true],'status'=>'unsorted']);$this->get($url)->assertNotFound();
    }
    public function test_public_article_images_render_in_a_clickable_gallery_and_lightbox(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('originals/article-cover.jpg','cover');
        Storage::disk('local')->put('originals/article-detail.jpg','detail');
        $cover=Media::create(['title'=>'Article cover','original_name'=>'article-cover.jpg','kind'=>'image','mime'=>'image/jpeg','bytes'=>5,'disk'=>'local','path'=>'originals/article-cover.jpg','source'=>'upload','source_id'=>'article-cover']);
        $detail=Media::create(['title'=>'Article detail','original_name'=>'article-detail.jpg','kind'=>'image','mime'=>'image/jpeg','bytes'=>6,'disk'=>'local','path'=>'originals/article-detail.jpg','source'=>'upload','source_id'=>'article-detail']);
        $record=$this->record(['kind'=>'post','title'=>'Visible article','metadata'=>['public_published'=>true,'media_ids'=>[$cover->id,$detail->id],'cover_media_id'=>$cover->id]]);
        $response=$this->get(app(PublicContent::class)->card($record)['url'])->assertOk();
        $response->assertSee('data-image-lightbox-dialog',false)->assertSee('data-image-lightbox',false)->assertSee('public-article-media-gallery',false)->assertSee('data-image-lightbox-next',false);
        $response->assertSee(route('public.media',[$record,$detail]),false);
    }
    public function test_public_video_uses_a_poster_backdrop_behind_contained_playback(): void
    {
        Storage::fake('local');Storage::disk('local')->put('originals/portrait.mp4','video');
        $video=Media::create(['title'=>'Portrait video','original_name'=>'portrait.mp4','kind'=>'video','mime'=>'video/mp4','bytes'=>5,'disk'=>'local','path'=>'originals/portrait.mp4','source'=>'upload','source_id'=>'portrait-video']);
        $record=$this->record(['metadata'=>['public_published'=>true,'media_ids'=>[$video->id]]]);
        $response=$this->get(app(PublicContent::class)->card($record)['url'])->assertOk();
        $response->assertSee('public-video-player-shell',false)->assertSee('--video-poster:url(',false)->assertSee('poster=',false)->assertSee(route('public.media',[$record,$video]),false);
    }
    public function test_public_media_requires_a_published_parent_and_exact_connection(): void
    {
        Storage::fake('local');Storage::disk('local')->put('originals/local.mp4','0123456789');
        $media=Media::create(['title'=>'Local video','original_name'=>'local.mp4','kind'=>'video','mime'=>'video/mp4','bytes'=>10,'disk'=>'local','path'=>'originals/local.mp4','source'=>'upload','source_id'=>'local-video']);
        $record=$this->record(['metadata'=>['public_published'=>true,'media_ids'=>[$media->id]]]);
        $url=route('public.media',[$record,$media]);
        $this->get($url)->assertOk()->assertHeader('Content-Type','video/mp4');
        $this->get($url,['Range'=>'bytes=0-3'])->assertStatus(206)->assertHeader('Content-Range','bytes 0-3/10');
        $record->update(['metadata'=>['public_published'=>true]]);$this->get($url)->assertNotFound();
        $record->update(['metadata'=>['media_ids'=>[$media->id]]]);$this->get($url)->assertNotFound();
    }
    public function test_publishing_requires_publish_permission_and_ready_content(): void
    {
        app(Access::class)->seed();$record=$this->record(['status'=>'unsorted']);
        $user=User::factory()->create();$user->roles()->attach(Role::where('name','Mediengestalter')->firstOrFail());
        $this->actingAs($user)->patchJson('/desktop/content/'.$record->id,['public_published'=>true])->assertForbidden();
        $user->roles()->sync([Role::where('name','Owner')->firstOrFail()->id]);$user->unsetRelation('roles');
        $data=['title'=>$record->title,'kind'=>'video'];
        $this->patchJson('/desktop/content/'.$record->id,[...$data,'status'=>'unsorted','public_published'=>true])->assertStatus(422);
        $this->patchJson('/desktop/content/'.$record->id,[...$data,'status'=>'ready','public_published'=>true])->assertOk();
        $this->assertTrue($record->fresh()->metadata['public_published']);
        $this->getJson('/desktop/content/'.$record->id)->assertOk()->assertJsonPath('private',false);
        $this->patchJson('/desktop/content/'.$record->id,[...$data,'status'=>'ready','public_published'=>true,'public_homepage'=>true])->assertOk();
        $this->assertTrue($record->fresh()->metadata['public_homepage']);
        $this->getJson('/desktop/content/'.$record->id)->assertJsonPath('public_homepage',true);
        $this->patchJson('/desktop/content/'.$record->id,[...$data,'status'=>'ready','public_published'=>false])->assertOk();
        $this->assertFalse($record->fresh()->metadata['public_published']);
    }
}
