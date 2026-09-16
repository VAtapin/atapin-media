<?php
namespace App\Services;

use App\Models\{Collection,Product,SourceRecord,PublicContentState,TaxonomyTerm};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PublicCatalog
{
    public function __construct(private PublicContent $content,private PublicBooks $books,private PublicParticipation $participation,private PublicCommunityModeration $communityModeration,private PublicTaxonomy $taxonomy) {}
    public function listing(Request $request,string $section): array
    {
        if($section==='live')$this->content->expireScheduledLives();
        $data=$request->validate(['q'=>'nullable|string|max:120','tag'=>'nullable|string|max:100','taxonomy'=>'nullable|string|max:180','sort'=>'nullable|in:latest,oldest,popular','series'=>'nullable|integer|min:1','page'=>'nullable|integer|min:1|max:100000']);
        $query=$section==='buecher'?$this->books->query():$this->content->withViewCounts($section==='search'?$this->content->query()->whereIn('kind',['video','short','post','poll']):$this->content->forSection($section));
        if($data['q']??'')$query->where(fn($q)=>$q->where('title','like','%'.$data['q'].'%')->orWhere($section==='buecher'?'description':'body','like','%'.$data['q'].'%')->when($section==='buecher',fn($q)=>$q->orWhere('contents','like','%'.$data['q'].'%')));
        $tagFilter = trim((string) ($data['tag'] ?? ''));
        $taxonomyFilter = $this->taxonomy->resolve($data['taxonomy'] ?? null);
        if (($data['taxonomy'] ?? '') !== '' && ! $taxonomyFilter) abort(404);
        if ($taxonomyFilter) $this->taxonomy->constrain($query, $section, $taxonomyFilter);
        if($tagFilter && ! $taxonomyFilter){
            if($section==='buecher')$query->where(fn($q)=>$q->whereJsonContains('metadata->tags',$data['tag'])->orWhere('title','like','%'.$data['tag'].'%')->orWhere('description','like','%'.$data['tag'].'%'));
        }
        if(($data['series']??null)&&$section!=='buecher'){
            $collection=Collection::where('metadata->public_published',true)->findOrFail($data['series']);
            $query->where(fn($q)=>$q->whereIn('id',$collection->items()->whereNotNull('source_record_id')->select('source_record_id'))->orWhere(fn($q)=>$q->where('source',$collection->source)->whereIn('source_id',$collection->items()->whereNull('source_record_id')->whereNotNull('source_id')->select('source_id'))));
        }
        $sort=$data['sort']??'latest';
        if($sort==='popular'&&$section!=='buecher')$query->orderByDesc('public_view_count');
        $query->orderBy('created_at',$sort==='oldest'?'asc':'desc')->orderByDesc('id');
        $mapper=$section==='buecher'?$this->books->card(...):$this->content->card(...);
        $perPage=$section==='beitraege'?9:8;
        if($tagFilter&&!$taxonomyFilter&&$section!=='buecher'){
            $tagged=$query->get()->filter(fn($record)=>$this->hasTag($record,$tagFilter))->values();
            $page=new LengthAwarePaginator($tagged->forPage($request->integer('page',1),$perPage)->values(),$tagged->count(),$perPage,$request->integer('page',1),['path'=>$request->url(),'query'=>$request->query()]);
        }else $page=$query->paginate($perPage)->withQueryString();
        $items=$page->through($mapper);
        $featured=$items->first();
        $record=null;
        if(in_array($section,['podcast','live','community'])){
            $key=['podcast'=>'episode','live'=>'event','community'=>'discussion'][$section];
            $request->validate([$key=>'nullable|integer|min:1']);
            $record=$request->filled($key)?$this->content->forSection($section)->findOrFail($request->integer($key)):null;
            if(!$record&&$section==='live')$record=$this->content->nextLive();
            if(!$record&&$featured)$record=$this->content->forSection($section)->find($featured['id']);
            if($record)$featured=$this->content->card($record);
        }
        $readingStates=$request->user()?PublicContentState::where('user_id',$request->user()->id)->where('subject_type','book')->whereIn('action',['bookmark','progress'])->get():collect();
        $readingBooks=$this->books->query()->whereIn('id',$readingStates->filter(fn($state)=>$state->action==='progress'||($state->value['enabled']??false))->pluck('subject_id'))->get()->map(function($book)use($readingStates){
            return [...$this->books->card($book),'progress'=>$readingStates->first(fn($state)=>$state->subject_id===$book->id&&$state->action==='progress')?->value['position']??null];
        });
        $resumeState=$request->user()?PublicContentState::where('user_id',$request->user()->id)->where('subject_type','record')->where('action','progress')->whereIn('subject_id',$this->content->forSection('podcast')->select('id'))->latest('updated_at')->first():null;
        $resumeRecord=$resumeState?$this->content->forSection('podcast')->find($resumeState->subject_id):null;
        $sessionId=$request->hasSession()?$request->session()->getId():null;
        $popularQuery=$section==='buecher'?$this->books->query()->latest():($section==='live'?$this->content->forSection('live')->where('metadata->live_status','ended')->latest():$this->content->withViewCounts($this->content->forSection($section))->orderByDesc('public_view_count')->latest()->orderByDesc('id'));
        if($taxonomyFilter)$this->taxonomy->constrain($popularQuery,$section,$taxonomyFilter);
        $taxonomyFilters=$this->taxonomy->filters($section);
        $searchBooks=collect();$searchTerms=collect();
        if($section==='search'){
            $needle=trim((string)($data['q']??''));
            $searchBooks=$this->books->query()->when($needle!=='',fn($q)=>$q->where(fn($q)=>$q->where('title','like','%'.$needle.'%')->orWhere('description','like','%'.$needle.'%')->orWhere('contents','like','%'.$needle.'%')))->latest()->limit(24)->get()->map($this->books->card(...));
            $links=[];foreach($this->taxonomy->directoryShelves() as $shelf)foreach($shelf['books'] as $topic)$links[$topic['id']]=$topic['url'];
            $searchTerms=TaxonomyTerm::where('active',true)->when($needle!=='',fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$needle.'%')->orWhere('description','like','%'.$needle.'%')))->orderBy('name')->limit(24)->get()->map(fn($term)=>['id'=>$term->id,'name'=>$term->name,'kind'=>$term->kind,'description'=>$term->description,'url'=>$term->kind==='category'?route('public.categories',['category'=>$term->slug]):($links[$term->id]??route('public.beitraege',['taxonomy'=>$term->slug]))]);
        }
        return ['items'=>$items,'searchBooks'=>$searchBooks,'searchTerms'=>$searchTerms,'featured'=>$featured,'readingBooks'=>$readingBooks,'resume'=>$resumeRecord?[...$this->content->card($resumeRecord),'position'=>$resumeState->value['position']??0]:null,
            'popular'=>$popularQuery->limit($section==='beitraege'?6:5)->get()->map($mapper),
            'topics'=>$this->topics($section),'taxonomyFilters'=>$taxonomyFilters,'selectedTaxonomy'=>$taxonomyFilter,'series'=>$this->series($section),
            'record'=>$record,'assets'=>$record?$this->content->assets($record):collect(),
            'comments'=>$record?$this->content->childrenForViewer($record,'comment',$request->user(),$sessionId)->latest()->paginate(20,['*'],'comments_page')->withQueryString()->fragment('comments'):collect(),
            'chat'=>$record?$this->content->childrenForViewer($record,'live_chat',$request->user(),$sessionId)->latest()->limit(30)->get()->reverse():collect(),
            'communityBlocked'=>$this->communityModeration->blocked($request->user(),$sessionId),
            'states'=>$record?$this->participation->mine($record,$request->user()):[],
            'poll'=>app(Polls::class)->current($section),
            'upcoming'=>$this->content->forSection('live')->where('metadata->live_status','scheduled')->get()->filter(fn($event)=>$this->content->hasFutureStart($event))->sortBy(fn($event)=>strtotime((string) ($event->metadata['starts_at']??'')))->take(5)->map($this->content->card(...)),
            'book'=>$this->books->query()->latest()->first()?->id? $this->books->card($this->books->query()->latest()->first()):null,
            'relatedVideo'=>$this->content->forSection('videos')->latest()->first()?->id?$this->content->card($this->content->forSection('videos')->latest()->first()):null];
    }
    public function topics(string $section): array
    {
        $topics=[];
        $labels=[];
        foreach(($section==='buecher'?$this->books->query():$this->content->forSection($section))->limit(2000)->pluck('metadata') as $metadata)
            foreach(array_unique(array_filter($metadata['tags']??[],'is_string')) as $tag){
                $label=$this->normalizeTag($tag);if($label==='')continue;
                $key=$this->tagKey($label);$labels[$key]??=$label;$topics[$key]=($topics[$key]??0)+1;
            }
        arsort($topics);$result=[];foreach(array_slice(array_keys($topics),0,10) as $key)$result[$labels[$key]]=$topics[$key];return $result;
    }
    private function hasTag(SourceRecord $record,string $tag): bool
    {
        $wanted=$this->tagKey($tag);
        return collect([...($record->metadata['tags']??[]),...($record->metadata['taxonomy_tags']??[])])->contains(fn($value)=>is_string($value)&&$this->tagKey($value)===$wanted);
    }
    private function normalizeTag(string $tag): string
    {
        $tag=preg_replace('/\s+/u',' ',trim($tag))??trim($tag);
        return class_exists('Normalizer') ? (\Normalizer::normalize($tag,\Normalizer::FORM_C)??$tag) : $tag;
    }
    private function tagKey(string $tag): string { return mb_strtolower($this->normalizeTag($tag),'UTF-8'); }
    public function series(string $section)
    {
        if(!in_array($section,['videos','podcast']))return collect();
        return Collection::where('metadata->public_published',true)->where(fn($q)=>$q->where('metadata->public_section',$section)->when($section==='videos',fn($q)=>$q->orWhereNull('metadata->public_section')))->limit(5)->get()->map(function($collection)use($section){
            return ['title'=>$collection->title,'excerpt'=>$collection->description,'count'=>$this->content->forSection($section)->where(fn($q)=>$q->whereIn('id',$collection->items()->whereNotNull('source_record_id')->select('source_record_id'))->orWhere(fn($q)=>$q->where('source',$collection->source)->whereIn('source_id',$collection->items()->whereNull('source_record_id')->select('source_id'))))->count(),
                'url'=>route('public.'.$section,['series'=>$collection->id]),'image'=>null,'author'=>'','meta'=>''];
        });
    }
    public function detail(Request $request,?SourceRecord $record,string $section): array
    {
        return ['record'=>$record,'card'=>$record?$this->content->card($record):null,'assets'=>$record?$this->content->assets($record):collect(),
            'related'=>$this->content->forSection($section)->when($record,fn($q)=>$q->whereKeyNot($record->id))->latest()->limit(5)->get()->map($this->content->card(...)),
            'comments'=>$record?$this->content->childrenForViewer($record,'comment',$request->user(),$request->hasSession()?$request->session()->getId():null)->latest()->paginate(20,['*'],'comments_page')->withQueryString()->fragment('comments'):collect(),
            'communityBlocked'=>$this->communityModeration->blocked($request->user(),$request->hasSession()?$request->session()->getId():null),
            'states'=>$record?$this->participation->mine($record,$request->user()):[],
            'book'=>($book=$this->books->query()->latest()->first())?$this->books->card($book):null,
            'relatedVideo'=>($video=$this->content->forSection('videos')->when($record,fn($q)=>$q->whereKeyNot($record->id))->latest()->first())?$this->content->card($video):null];
    }
    public function bookDetail(Request $request,?Product $book): array
    {
        return ['bookRecord'=>$book,'card'=>$book?$this->books->card($book):null,'assets'=>$book?$this->books->assets($book):collect(),
            'reviews'=>$book?\App\Models\BookReview::with('user')->where('product_id',$book->id)->where('status','published')->latest()->paginate(20,['*'],'reviews_page')->withQueryString():collect(),
            'states'=>$book?$this->participation->mine($book,$request->user()):[],
            'relatedBooks'=>$this->books->query()->when($book,fn($q)=>$q->whereKeyNot($book->id))->latest()->limit(4)->get()->map($this->books->card(...)),
            'relatedVideo'=>($video=$this->content->forSection('videos')->latest()->first())?$this->content->card($video):null,
            'relatedArticle'=>($post=$this->content->forSection('beitraege')->latest()->first())?$this->content->card($post):null];
    }
}
