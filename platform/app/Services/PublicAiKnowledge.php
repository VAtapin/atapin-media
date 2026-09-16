<?php

namespace App\Services;

use App\Models\{Product,SourceRecord,TaxonomyTerm};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PublicAiKnowledge
{
    public function __construct(private PublicContent $content,private PublicBooks $books) {}

    public function context(?SourceRecord $live,string $question): array
    {
        $tokens=array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($question),-1,PREG_SPLIT_NO_EMPTY)?:[],
            fn(string $token)=>mb_strlen($token)>=3&&!in_array($token,['какие','есть','сайте','where','what','welche','gibt','eine','einem','und','для','the'],true)
        )));
        $bookIntent=(bool)preg_match('/книг|buch|bücher|book/iu',$question);
        $topicIntent=(bool)preg_match('/тем|категор|thema|topic|categor/iu',$question);
        $recordIntent=(bool)preg_match('/видео|video|лекц|интерв|podcast|подкаст|опрос|poll|umfrage|beitrag|article|стать|материал/iu',$question);
        $source=$this->content->query()->whereIn('kind',['video','short','post','poll']);
        if(!$recordIntent)$this->match($source,$tokens,['title','body']);
        if(preg_match('/podcast|подкаст/iu',$question))$source->where('metadata->public_section','podcast');
        if(preg_match('/опрос|poll|umfrage/iu',$question))$source->where('kind','poll');
        $found=$source->latest('id')->limit($recordIntent?100:12)->get();
        $recordTitles=$recordIntent?$found->map(fn(SourceRecord $record)=>['type'=>$this->content->section($record),'title'=>$record->title])->all():[];
        if($live)$found->prepend($live);
        $records=$found->unique('id')->take(12)->values()
            ->map(fn(SourceRecord $record)=>['type'=>$this->content->section($record),'title'=>mb_substr($record->title,0,200),
                'text'=>$this->excerpt(strip_tags((string)$record->body),$tokens,900),'url'=>$this->recordUrl($record)])->all();

        $bookQuery=$this->books->query();
        if(!$bookIntent)$this->match($bookQuery,$tokens,['title','description','contents']);
        $allBooks=$bookQuery->orderBy('title')->limit($bookIntent?100:12)->get();
        $bookDetails=$allBooks->take(8)->map(fn(Product $book)=>['title'=>$book->title,'author'=>$book->author,
            'description'=>$this->excerpt(strip_tags((string)$book->description),$tokens,800),
            'contents'=>$this->excerpt(strip_tags((string)$book->contents),$tokens,1000),
            'url'=>route('public.book',['slug'=>Str::slug($book->title).'-'.$book->id])])->all();
        $bookTitles=$bookIntent?$allBooks->pluck('title')->all():[];

        $termQuery=TaxonomyTerm::where('active',true);
        if(!$topicIntent)$this->match($termQuery,$tokens,['name','description']);
        $terms=$termQuery->orderBy('name')->limit(30)->get()->map(fn(TaxonomyTerm $term)=>[
            'type'=>$term->kind,'name'=>$term->name,'description'=>mb_substr((string)$term->description,0,350),
            'url'=>$term->kind==='category'?route('public.categories',['category'=>$term->slug]):route('public.categories')])->all();

        return ['published_records'=>$records,'published_record_count'=>$this->content->query()->whereIn('kind',['video','short','post','poll'])->count(),
            'published_record_titles'=>$recordTitles,'published_record_titles_truncated'=>$recordIntent&&$source->count()>count($recordTitles),
            'active_book_count'=>$this->books->query()->count(),
            'book_titles'=>$bookTitles,'book_titles_truncated'=>$bookIntent&&$this->books->query()->count()>count($bookTitles),
            'book_details'=>$bookDetails,'active_topics_and_categories'=>$terms,
            'active_topic_and_category_count'=>TaxonomyTerm::where('active',true)->count(),
            'navigation'=>collect(config('public_ui.navigation'))->map(fn($item,$key)=>['label'=>__('public.nav_'.$key),'path'=>$item['path']])->values()->all(),
            'search_path'=>'/suche','server_clock'=>now(config('app.timezone'))->toIso8601String(),'timezone'=>config('app.timezone')];
    }

    private function match(Builder $query,array $tokens,array $columns): void
    {
        if(!$tokens)return;
        $query->where(function(Builder $query)use($tokens,$columns){
            foreach(array_slice($tokens,0,6) as $token)foreach($columns as $column)
                $query->orWhere($column,'like','%'.$token.'%');
        });
    }

    private function recordUrl(SourceRecord $record): string
    {
        $section=$this->content->section($record);
        $slug=(($record->metadata['slug']??null)?:Str::slug($record->title)).'-'.$record->id;
        return match($section){'videos'=>route('public.video',['slug'=>$slug]),
            'beitraege'=>route('public.article',['slug'=>$slug]),
            'podcast'=>route('public.podcast',['episode'=>$record->id]),
            'live'=>route('public.live',['event'=>$record->id]),
            default=>route('public.community',['discussion'=>$record->id])};
    }

    private function excerpt(string $text,array $tokens,int $limit): string
    {
        $text=trim(preg_replace('/\s+/u',' ',$text)??$text);
        $start=0;
        foreach($tokens as $token){$position=mb_stripos($text,$token);if($position!==false){$start=max(0,$position-100);break;}}
        return mb_substr($text,$start,$limit);
    }
}
