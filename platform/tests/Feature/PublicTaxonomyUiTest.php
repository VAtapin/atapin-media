<?php

namespace Tests\Feature;

use App\Models\{Product, SourceRecord, TaxonomyTerm};
use App\Services\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTaxonomyUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_catalogs_render_section_specific_taxonomy_navigation(): void
    {
        $category = TaxonomyTerm::create(['kind'=>'category','name'=>'Medizin','slug'=>'medizin','active'=>true]);
        $topic = TaxonomyTerm::create(['kind'=>'topic','name'=>'Anatomie','slug'=>'anatomie','parent_id'=>$category->id,'active'=>true]);
        $video = $this->record('video', 'Anatomie Video');
        $article = $this->record('post', 'Anatomie Beitrag');
        $book = Product::create(['title'=>'Anatomie Buch','status'=>'active','currency'=>'EUR']);

        $taxonomy = app(Taxonomy::class);
        $taxonomy->sync($video, [$topic->id]);
        $taxonomy->sync($article, [$topic->id]);
        $taxonomy->sync($book, [$topic->id]);

        $home = $this->get('/')->assertOk()->assertSee('data-public-home-topics', false)->assertSee('Anatomie');
        foreach (['videos','beitraege','buecher'] as $section) {
            $home->assertSee(route('public.'.$section, ['taxonomy'=>'anatomie']), false);
        }

        foreach (['videos'=>'Anatomie Video','beitraege'=>'Anatomie Beitrag','buecher'=>'Anatomie Buch'] as $section=>$title) {
            $response = $this->get('/'.$section)->assertOk()->assertSee('data-public-taxonomy', false)
                ->assertSee(__('public.explore_'.$section.'_by_topic'))->assertSee('Medizin')->assertSee('Anatomie');
            $response->assertViewHas('taxonomyFilters', fn (array $filters) => collect($filters)->contains(
                fn (array $term) => $term['slug']==='anatomie' && $term['count']===1
            ));
            $this->get('/'.$section.'?taxonomy=medizin')->assertOk()->assertSee($title)
                ->assertSee('aria-current="page"', false);
        }
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
