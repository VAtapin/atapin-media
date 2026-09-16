<?php

namespace Tests\Feature;

use App\Models\{Product, SourceRecord, TaxonomyTerm};
use App\Services\{PublicTaxonomy, Taxonomy};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_taxonomy_filters_recursively_without_mixing_public_sections(): void
    {
        [$medicine, $anatomy, $biomechanics] = $this->tree();
        $video = $this->record('video', 'Anatomy video', 'videos');
        $otherVideo = $this->record('video', 'Unrelated video', 'videos');
        $article = $this->record('post', 'Anatomy article', 'beitraege');
        $book = Product::create(['title' => 'Anatomy book', 'status' => 'active']);
        $taxonomy = app(Taxonomy::class);
        foreach ([$video, $article, $book] as $subject) $taxonomy->sync($subject, [$biomechanics->id]);

        $this->get('/videos?taxonomy='.$medicine->slug)->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()['id'] === $video->id)
            ->assertViewHas('popular', fn ($items) => $items->count() === 1 && $items->first()['id'] === $video->id);
        $this->get('/beitraege?taxonomy='.$medicine->id)->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()['id'] === $article->id);
        $this->get('/buecher?taxonomy='.$anatomy->slug)->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()['id'] === $book->id);

        $this->assertNotSame($video->id, $otherVideo->id);
    }

    public function test_legacy_tag_links_remain_metadata_based_when_a_taxonomy_name_matches(): void
    {
        [$medicine, , $biomechanics] = $this->tree();
        $assigned = $this->record('video', 'Taxonomy-assigned video', 'videos');
        $legacy = $this->record('video', 'Legacy-tagged video', 'videos');
        $legacy->update(['metadata' => [...$legacy->metadata, 'tags' => [$medicine->name]]]);
        app(Taxonomy::class)->sync($assigned, [$biomechanics->id]);

        $this->get('/videos?tag='.urlencode($medicine->name))->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1 && $items->first()['id'] === $legacy->id);
        $this->get('/videos?taxonomy=does-not-exist')->assertNotFound();
    }

    public function test_taxonomy_filters_are_data_driven_and_count_only_the_current_section(): void
    {
        [$medicine, $anatomy, $biomechanics] = $this->tree();
        $first = $this->record('video', 'First anatomy video', 'videos');
        $second = $this->record('short', 'Second anatomy video', 'videos');
        $article = $this->record('post', 'Anatomy contribution', 'beitraege');
        $taxonomy = app(Taxonomy::class);
        foreach ([$first, $second, $article] as $subject) $taxonomy->sync($subject, [$biomechanics->id]);

        $this->get('/videos')->assertOk()
            ->assertViewHas('taxonomyFilters', function (array $filters) use ($medicine, $anatomy) {
                $parent = collect($filters)->firstWhere('id', $medicine->id);
                $topic = collect($filters)->firstWhere('id', $anatomy->id);
                return $parent['count'] === 2
                    && $parent['kind'] === 'category'
                    && $parent['parent_name'] === null
                    && $parent['url'] === route('public.videos', ['taxonomy' => $medicine->slug])
                    && $topic['count'] === 2
                    && $topic['parent_name'] === $medicine->name;
            })
            ->assertViewHas('topics', fn (array $topics) => ! array_key_exists($medicine->name, $topics));

        $this->get('/beitraege')->assertOk()->assertViewHas('taxonomyFilters', fn (array $filters) => collect($filters)->firstWhere('id', $medicine->id)['count'] === 1);
    }

    public function test_home_topics_keep_section_destinations_separate_and_deduplicate_assignments(): void
    {
        [$medicine, , $biomechanics] = $this->tree();
        $video = $this->record('video', 'Home anatomy video', 'videos');
        $article = $this->record('post', 'Home anatomy article', 'beitraege');
        $book = Product::create(['title' => 'Home anatomy book', 'status' => 'active']);
        $taxonomy = app(Taxonomy::class);
        $taxonomy->sync($video, [$medicine->id, $biomechanics->id]);
        $taxonomy->sync($article, [$biomechanics->id]);
        $taxonomy->sync($book, [$biomechanics->id]);

        $topic = collect(app(PublicTaxonomy::class)->homeTopics())->firstWhere('id', $biomechanics->id);
        $this->assertSame(3, $topic['total']);
        $this->assertSame(1, $topic['sections']['videos']['count']);
        $this->assertSame(1, $topic['sections']['beitraege']['count']);
        $this->assertSame(1, $topic['sections']['buecher']['count']);
        $this->assertSame(route('public.videos', ['taxonomy' => $biomechanics->slug]), $topic['sections']['videos']['url']);
        $this->assertSame(route('public.beitraege', ['taxonomy' => $biomechanics->slug]), $topic['sections']['beitraege']['url']);
        $this->assertSame(route('public.buecher', ['taxonomy' => $biomechanics->slug]), $topic['sections']['buecher']['url']);
        $this->get('/')->assertOk()->assertViewHas('homeTopics', fn (array $topics) => collect($topics)->firstWhere('id', $biomechanics->id)['total'] === 3);
    }

    public function test_home_navigation_keeps_all_categories_and_topics_at_realistic_scale(): void
    {
        $taxonomy = app(Taxonomy::class);
        foreach (range(1, 10) as $categoryNumber) {
            $category = TaxonomyTerm::create(['name'=>'Category '.$categoryNumber,
                'slug'=>'category-'.$categoryNumber,'kind'=>'category','active'=>true]);
            foreach (range(1, 5) as $topicNumber) {
                $topic = TaxonomyTerm::create(['name'=>'Topic '.$categoryNumber.'-'.$topicNumber,
                    'slug'=>'topic-'.$categoryNumber.'-'.$topicNumber,'kind'=>'topic',
                    'parent_id'=>$category->id,'active'=>true]);
                $taxonomy->sync($this->record('post', 'Article '.$categoryNumber.'-'.$topicNumber, 'beitraege'), [$topic->id]);
                if ($categoryNumber === 1 && $topicNumber === 1) foreach (range(2, 12) as $articleNumber) {
                    $taxonomy->sync($this->record('post', 'Article 1-1-'.$articleNumber, 'beitraege'), [$topic->id]);
                }
            }
        }

        $topics = app(PublicTaxonomy::class)->homeTopics();
        $this->assertCount(50, $topics);
        $this->assertCount(10, collect($topics)->pluck('category_id')->unique());
        $this->assertSame(12, collect($topics)->firstWhere('slug', 'topic-1-1')['sections']['beitraege']['count']);
        $this->assertCount(12, app(PublicTaxonomy::class)->homeTopics(12));
        $this->get('/')->assertOk()->assertViewHas('homeTopics', fn (array $items) => count($items) === 50);
        $this->get('/themen')->assertOk()->assertViewHas('directoryShelves', fn (array $shelves) =>
            count($shelves) === 10 && collect($shelves)->sum(fn (array $shelf) => count($shelf['books'])) === 50);
    }

    private function tree(): array
    {
        $medicine = TaxonomyTerm::create(['name' => 'Medizin', 'slug' => 'medizin', 'kind' => 'category', 'active' => true]);
        $anatomy = TaxonomyTerm::create(['name' => 'Anatomie', 'slug' => 'anatomie', 'kind' => 'topic', 'parent_id' => $medicine->id, 'active' => true]);
        $biomechanics = TaxonomyTerm::create(['name' => 'Biomechanik', 'slug' => 'biomechanik', 'kind' => 'topic', 'parent_id' => $anatomy->id, 'active' => true]);
        return [$medicine, $anatomy, $biomechanics];
    }

    private function record(string $kind, string $title, string $section): SourceRecord
    {
        return SourceRecord::create([
            'source' => 'website',
            'source_id' => (string) Str::uuid(),
            'kind' => $kind,
            'title' => $title,
            'body' => $title.' body',
            'status' => 'ready',
            'metadata' => ['public_published' => true, 'public_section' => $section],
        ]);
    }
}
