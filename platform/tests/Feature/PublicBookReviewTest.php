<?php
namespace Tests\Feature;
use App\Models\{User,Product,BookReview,Role};
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PublicBookReviewTest extends TestCase {
    use RefreshDatabase;
    public function test_book_metadata_and_review_moderation_are_database_driven(): void {
        $book=Product::create(['title'=>'Test book','author'=>'Real author','contents'=>'Chapter one','status'=>'active','price_cents'=>100,'currency'=>'EUR']);
        $this->get('/buecher/test-book-'.$book->id)->assertOk()->assertSee('Real author')->assertSee('Chapter one');
        $reader=User::factory()->create();$this->actingAs($reader)->post('/buecher/'.$book->id.'/reviews',['rating'=>5,'body'=>'Reader review'])->assertRedirect();
        $review=BookReview::firstOrFail();$this->get('/buecher/test-book-'.$book->id)->assertDontSee('Reader review');
        $this->patch('/desktop/shop/reviews/'.$review->id,['status'=>'published'])->assertForbidden();
        app(Access::class)->seed();$owner=User::factory()->create();$owner->roles()->attach(Role::where('name','Owner')->firstOrFail());
        $this->actingAs($owner)->get('/desktop/shop/products')->assertOk();
        $this->patch('/desktop/shop/reviews/'.$review->id,['status'=>'published'])->assertRedirect();$this->get('/buecher/test-book-'.$book->id)->assertSee('Reader review');
        $this->actingAs($reader)->post('/buecher/'.$book->id.'/reviews',['rating'=>4,'body'=>'Edited private review'])->assertRedirect();$this->assertSame(1,BookReview::count());$this->assertSame('pending',$review->fresh()->status);
    }
}
