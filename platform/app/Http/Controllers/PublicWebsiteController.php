<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\PublicContent;
use App\Services\{PublicCatalog,PublicBooks,PublicTaxonomy};
use App\Services\PublicViewCounter;
use Illuminate\Http\Request;

class PublicWebsiteController extends Controller
{
    private function shared(): array
    {
        return ['siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),
            'description'=>__('public.hero_intro'),'heroImage'=>config('public_ui.hero_image')];
    }
    public function home(PublicContent $content, PublicTaxonomy $taxonomy)
    {
        $videos=$content->withViewCounts($content->forSection('videos'))->latest()->limit(3)->get()->map($content->card(...));
        $articles=$content->forSection('beitraege')->latest()->limit(3)->get()->map($content->card(...));
        $books=app(PublicBooks::class);$book=$books->query()->latest()->first();
        $live=$content->nextLive();
        $featuredRecord=$content->homepageVideos()->latest()->orderByDesc('id')->first()??$content->forSection('videos')->latest()->orderByDesc('id')->first();
        return view('public.home',[...$this->shared(),'videos'=>$videos,'articles'=>$articles,
            'featured'=>$featuredRecord?$content->card($featuredRecord):null,'book'=>$book?$books->card($book):null,
            'live'=>$live?$content->card($live):null,'homeTopics'=>$taxonomy->homeTopics()]);
    }
    public function recordView(Request $request, SourceRecord $record, PublicContent $content, PublicViewCounter $counter)
    {
        abort_unless(in_array($record->kind,['video','short'],true)&&$content->visible($record)&&$content->section($record)==='videos',404);
        return response()->json(['views'=>$counter->record($record,$request)])->header('Cache-Control','no-store');
    }
    public function listing(Request $request,PublicContent $content)
    {
        $section=$request->route('section');
        if(in_array($section,['videos','beitraege','buecher','live','podcast','community','search'])){
            $data=app(PublicCatalog::class)->listing($request,$section);
            return view('public.'.($section==='search'?'section':$section),[...$this->shared(),'section'=>$section,
                'layoutMode'=>$section==='live'&&$request->filled('event')?'detail':'overview',...$data]);
        }
        $data=$request->validate(['q'=>'nullable|string|max:120']);
        $section=$request->route('section');$query=$content->query();
        if($section==='videos')$query->whereIn('kind',['video','short']);
        elseif($section==='beitraege')$query->where('kind','post');
        elseif($section!=='search')$query->whereRaw('1=0');
        if($data['q']??'')$query->where(fn($q)=>$q->where('title','like','%'.$data['q'].'%')->orWhere('body','like','%'.$data['q'].'%'));
        $page=$query->latest()->paginate(12)->withQueryString();
        $settings=app(\App\Services\Settings::class);
        $key=['impressum'=>'impressum','datenschutz'=>'privacy_policy','ueber-uns'=>'about_text','unsere-mission'=>'mission_text'][$section]??null;
        $document=$key?($settings->get('legal_documents',[])[app()->getLocale()][$key]??''):'';
        $request->validate(['book'=>'nullable|integer|min:1']);
        $orderBook=$request->filled('book')?app(PublicBooks::class)->query()->findOrFail($request->integer('book')):null;
        return view('public.section',[...$this->shared(),'section'=>$section,'orderBook'=>$orderBook,'items'=>$page->through($content->card(...)),
            'document'=>$document, 'contactEmail'=>$settings->get('contact_email','')]);
    }
    public function detail(Request $request,string $slug,PublicContent $content)
    {
        $section=$request->route('section');
        if($slug==='vorschau')return view('public.'.($section==='videos'?'video':'beitrag'),[...$this->shared(),'section'=>$section,'layoutMode'=>'detail',...app(PublicCatalog::class)->detail($request,null,$section)]);
        abort_unless(preg_match('/-(\d+)$/',$slug,$matches),404);
        $section=$request->route('section');
        $record=$content->forSection($section)->findOrFail($matches[1]);
        $card=$content->card($record);if($request->url()!==$card['url'])return redirect($card['url'],301);
        return view('public.'.($section==='videos'?'video':'beitrag'),[...$this->shared(),'section'=>$section,'layoutMode'=>'detail',...app(PublicCatalog::class)->detail($request,$record,$section)]);
    }
    public function media(SourceRecord $record,Media $media,PublicContent $content)
    {
        abort_unless($content->visible($record)&&$content->assets($record)->contains('id',$media->id),404);
        if ($url = $media->publicUrl()) return redirect($url);
        return app(MediaController::class)->preview($media);
    }
    public function book(Request $request,string $slug,PublicBooks $books,PublicCatalog $catalog)
    {
        $book=null;
        if($slug!=='vorschau'){
            abort_unless(preg_match('/-(\d+)$/',$slug,$matches),404);$book=$books->query()->findOrFail($matches[1]);
            $url=$books->card($book)['url'];if($url!==$request->url())return redirect($url,301);
        }
        return view('public.buch',[...$this->shared(),'section'=>'buecher','layoutMode'=>'detail',...$catalog->bookDetail($request,$book)]);
    }
    public function bookMedia(\App\Models\Product $product,Media $media,PublicBooks $books)
    {
        abort_unless($books->query()->whereKey($product->id)->exists()&&$books->assets($product)->contains('id',$media->id),404);
        return app(MediaController::class)->preview($media);
    }
}
