<?php
namespace Tests\Feature;
use App\Models\{User,Product,BookReview,Role,Permission};
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
        $this->actingAs($owner)->get('/desktop/shop/products')->assertRedirect('/desktop?open=books-pdf')->assertStatus(303);
        $this->patch('/desktop/shop/reviews/'.$review->id,['status'=>'published'])->assertRedirect();$this->get('/buecher/test-book-'.$book->id)->assertSee('Reader review');
        $this->actingAs($reader)->post('/buecher/'.$book->id.'/reviews',['rating'=>4,'body'=>'Edited private review'])->assertRedirect();$this->assertSame(1,BookReview::count());$this->assertSame('pending',$review->fresh()->status);
    }

    public function test_native_desktop_review_queue_is_filtered_and_requires_both_permissions(): void
    {
        app(Access::class)->seed();
        $book = Product::create(['title'=>'Native review book','status'=>'active','price_cents'=>100,'currency'=>'EUR']);
        $reader = User::factory()->create(['name'=>'Review Reader']);
        $pending = BookReview::create(['product_id'=>$book->id,'user_id'=>$reader->id,'rating'=>5,'body'=>'Pending native feedback','status'=>'pending']);
        $rejectedReader = User::factory()->create();
        BookReview::create(['product_id'=>$book->id,'user_id'=>$rejectedReader->id,'rating'=>3,'body'=>'Already rejected','status'=>'rejected']);

        $shopRole = Role::create(['name'=>'Book manager']);
        $shopRole->permissions()->attach(Permission::whereIn('name',['desktop.view','shop.manage'])->pluck('id'));
        $shopManager = User::factory()->create();
        $shopManager->roles()->attach($shopRole);
        $this->actingAs($shopManager)->getJson('/desktop/book-reviews')->assertForbidden();
        $this->get('/desktop/workspaces/books-pdf')->assertOk()->assertSee('data-can-moderate-reviews="false"', false);

        $moderator = User::factory()->create();
        $moderator->roles()->attach(Role::where('name','Moderator')->firstOrFail());
        $this->actingAs($moderator)->getJson('/desktop/book-reviews')->assertForbidden();
        $this->get('/desktop/workspaces/books-pdf')->assertForbidden();
        $this->patch('/desktop/shop/reviews/'.$pending->id, ['status'=>'published'])->assertForbidden();

        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name','Owner')->firstOrFail());
        $response = $this->actingAs($owner)->getJson('/desktop/book-reviews?status=pending&q=Native');
        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.product.title', 'Native review book')
            ->assertJsonPath('data.0.user.name', 'Review Reader')
            ->assertJsonMissingPath('data.0.user.email');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->get('/desktop/workspaces/books-pdf')->assertOk()->assertSee('data-can-moderate-reviews="true"', false);
        $this->get('/desktop')->assertOk()->assertDontSee('href="'.route('shop.products').'"', false)->assertDontSee(__('public.book_manage'));

        $this->patchJson('/desktop/book-reviews/'.$pending->id, ['status'=>'published'])
            ->assertOk()->assertJsonPath('status', 'published');
        $this->assertDatabaseHas('audit_events', ['action'=>'shop.review_moderated', 'subject'=>(string) $pending->id]);
        $this->getJson('/desktop/book-reviews?status=published')->assertJsonPath('total', 1);
        $this->patchJson('/desktop/book-reviews/'.$pending->id, ['status'=>'pending'])->assertUnprocessable();

        $this->patch('/desktop/shop/reviews/'.$pending->id, ['status'=>'rejected'])->assertRedirect();
        $this->assertSame('rejected', $pending->fresh()->status);
    }
}
