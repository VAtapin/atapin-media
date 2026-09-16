<?php

namespace Tests\Feature;

use App\Models\{Media, Product, SourceRecord, TaxonomyTerm};
use App\Services\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTaxonomyUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_catalogs_render_section_specific_taxonomy_navigation(): void
    {
        $cover = Media::create(['title'=>'Medizin cover','original_name'=>'medizin.jpg','kind'=>'image',
            'mime'=>'image/jpeg','bytes'=>5,'disk'=>'media-canonical','path'=>str_repeat('a',64).'.jpg',
            'source'=>'taxonomy-test','source_id'=>'medizin-cover']);
        $category = TaxonomyTerm::create(['kind'=>'category','name'=>'Medizin','slug'=>'medizin','active'=>true,
            'cover_media_id'=>$cover->id]);
        $topic = TaxonomyTerm::create(['kind'=>'topic','name'=>'Anatomie','slug'=>'anatomie','parent_id'=>$category->id,'active'=>true]);
        $video = $this->record('video', 'Anatomie Video');
        $article = $this->record('post', 'Anatomie Beitrag');
        $book = Product::create(['title'=>'Anatomie Buch','status'=>'active','currency'=>'EUR']);

        $taxonomy = app(Taxonomy::class);
        $taxonomy->sync($video, [$topic->id]);
        $taxonomy->sync($article, [$topic->id]);
        $taxonomy->sync($book, [$topic->id]);

        $home = $this->get('/')->assertOk()->assertSee('data-public-book-shelf', false)
            ->assertSee('/assets/book-shelf/shelf.png', false)
            ->assertSee('public-book-shelf-book', false)->assertSee('Medizin')->assertSee('Anatomie')
            ->assertSee('href="'.route('public.beitraege', ['taxonomy'=>'anatomie']).'"', false)
            ->assertDontSee('public-taxonomy-crumbs', false)
            ->assertDontSee($cover->publicUrl(), false);
        $home->assertViewHas('homeTopics', fn (array $topics) => collect($topics)->firstWhere('id', $topic->id)['category_cover_url'] === $cover->publicUrl());

        foreach (['videos'=>'Anatomie Video','buecher'=>'Anatomie Buch'] as $section=>$title) {
            $response = $this->get('/'.$section)->assertOk()->assertSee('data-public-taxonomy', false)
                ->assertSee('public-taxonomy-crumbs', false)->assertSee('Medizin')->assertSee('Anatomie')
                ->assertSee($cover->publicUrl(), false)
                ->assertDontSee('public-taxonomy-heading', false)->assertDontSee('public-taxonomy-group', false);
            $response->assertViewHas('taxonomyFilters', fn (array $filters) => collect($filters)->contains(
                fn (array $term) => $term['slug']==='anatomie' && $term['count']===1 && $term['category_id']===$category->id
            ));
            $this->get('/'.$section.'?taxonomy=medizin')->assertOk()->assertSee($title)
                ->assertSee('aria-current="page"', false);
        }
        $this->get('/beitraege')->assertOk()->assertSee('data-public-book-shelf', false)
            ->assertSee('Medizin')->assertSee('Anatomie')
            ->assertSee('href="'.route('public.beitraege', ['taxonomy'=>'anatomie']).'"', false)
            ->assertDontSee('public-taxonomy-crumbs', false);
        $this->get('/beitraege?taxonomy=medizin')->assertOk()->assertSee('Anatomie Beitrag');
    }

    public function test_home_never_uses_protected_media_preview_for_a_category_cover(): void
    {
        $private = Media::create(['title'=>'Private cover','original_name'=>'private.jpg','kind'=>'image',
            'mime'=>'image/jpeg','bytes'=>5,'disk'=>'local','path'=>'private/private.jpg',
            'source'=>'taxonomy-test','source_id'=>'private-cover']);
        $category = TaxonomyTerm::create(['kind'=>'category','name'=>'Private category','slug'=>'private-category',
            'active'=>true,'cover_media_id'=>$private->id]);
        $topic = TaxonomyTerm::create(['kind'=>'topic','name'=>'Public topic','slug'=>'public-topic',
            'parent_id'=>$category->id,'active'=>true]);
        app(Taxonomy::class)->sync($this->record('post', 'Public article'), [$topic->id]);

        $this->get('/')->assertOk()->assertSee('Private category')
            ->assertDontSee(route('media.preview', $private), false)
            ->assertViewHas('homeTopics', fn (array $topics) => collect($topics)->firstWhere('id', $topic->id)['category_cover_url'] === null);
        $this->get('/beitraege')->assertOk()->assertDontSee(route('media.preview', $private), false)
            ->assertViewHas('taxonomyFilters', fn (array $filters) => collect($filters)->firstWhere('id', $category->id)['cover_url'] === null);
    }

    public function test_empty_category_still_has_a_shelf_and_its_own_plant(): void
    {
        $category = TaxonomyTerm::create(['kind'=>'category','name'=>'Empty category',
            'slug'=>'empty-category','active'=>true]);

        $this->assertSame([['id'=>$category->id,'name'=>'Empty category']], app(\App\Services\PublicTaxonomy::class)->homeCategories());
        $this->get('/')->assertOk()->assertSee('Empty category')
            ->assertSee('data-shelf-plant-left', false)
            ->assertSee('data-shelf-category="0"', false)
            ->assertDontSee('public-book-shelf-book-1', false);
    }

    public function test_category_directory_keeps_unpublished_topics_on_separate_shelves(): void
    {
        $medicine = TaxonomyTerm::create(['kind'=>'category','name'=>'Medizin','slug'=>'medizin','active'=>true]);
        $faith = TaxonomyTerm::create(['kind'=>'category','name'=>'Glaube','slug'=>'glaube','active'=>true]);
        $topic = TaxonomyTerm::create(['kind'=>'topic','name'=>'Anatomie und Bibelwissen',
            'slug'=>'anatomie-bibelwissen','parent_id'=>$medicine->id,'active'=>true]);
        TaxonomyTerm::create(['kind'=>'topic','name'=>'Gebet','slug'=>'gebet','parent_id'=>$faith->id,'active'=>true]);
        app(Taxonomy::class)->sync($this->record('post', 'Published anatomy'), [$topic->id]);

        $this->get('/themen')->assertOk()->assertSee('data-book-cabinet', false)
            ->assertSee('Anatomie und Bibelwissen')->assertSee('Gebet')
            ->assertSee('href="'.route('public.beitraege', ['taxonomy'=>'anatomie-bibelwissen']).'"', false)
            ->assertSee('href="'.route('public.beitraege', ['taxonomy'=>'gebet']).'"', false)
            ->assertSee('public-overview-hero', false);
        $this->assertCount(2, app(\App\Services\PublicTaxonomy::class)->directoryShelves());
    }

    private function record(string $kind, string $title): SourceRecord
    {
        return SourceRecord::create([
            'source'=>'taxonomy-ui-test',
            'source_id'=>strtolower(str_replace(' ', '-', $title)),
            'kind'=>$kind,
            'title'=>$title,
            'body'=>'Published taxonomy test content.',
            'status'=>'ready',
            'metadata'=>['public_published'=>true],
        ]);
    }
}
