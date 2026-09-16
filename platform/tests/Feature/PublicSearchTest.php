<?php

namespace Tests\Feature;

use App\Models\{Product,SourceRecord,TaxonomyTerm};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_only_published_materials_active_books_and_active_topics(): void
    {
        SourceRecord::create(['source'=>'website','source_id'=>'visible-lecture','kind'=>'video','title'=>'Visible lecture',
            'body'=>'Interview transcript','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'videos']]);
        SourceRecord::create(['source'=>'website','source_id'=>'private-lecture','kind'=>'video','title'=>'Private lecture',
            'body'=>'Hidden interview transcript','status'=>'ready','metadata'=>['public_published'=>false]]);
        SourceRecord::create(['source'=>'website','source_id'=>'visible-podcast','kind'=>'post','title'=>'Visible podcast episode',
            'body'=>'Audio discussion','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'podcast']]);
        SourceRecord::create(['source'=>'website','source_id'=>'visible-poll','kind'=>'poll','title'=>'Visible poll',
            'body'=>'Choose an answer','status'=>'ready','metadata'=>['public_published'=>true,'public_section'=>'community']]);
        Product::create(['title'=>'Visible book','description'=>'Public synopsis','contents'=>'Distinctive chapter heading',
            'status'=>'active','price_cents'=>0,'currency'=>'EUR']);
        Product::create(['title'=>'Draft book','description'=>'Private synopsis','contents'=>'Hidden chapter heading',
            'status'=>'draft','price_cents'=>0,'currency'=>'EUR']);
        TaxonomyTerm::create(['kind'=>'category','name'=>'Visible category','slug'=>'visible-category','description'=>'Family and faith','active'=>true]);
        TaxonomyTerm::create(['kind'=>'topic','name'=>'Inactive topic','slug'=>'inactive-topic','description'=>'Hidden insight','active'=>false]);

        $this->get('/suche?q=chapter')->assertOk()->assertSee('Visible book')->assertDontSee('Draft book');
        $this->get('/suche?q=lecture')->assertOk()->assertSee('Visible lecture')->assertDontSee('Private lecture');
        $this->get('/suche?q=podcast')->assertOk()->assertSee('Visible podcast episode');
        $this->get('/suche?q=poll')->assertOk()->assertSee('Visible poll');
        $this->get('/suche?q=family')->assertOk()->assertSee('Visible category')->assertDontSee('Inactive topic');
        $this->get('/buecher?q=chapter')->assertOk()->assertSee('Visible book')->assertDontSee('Draft book');
    }
}
