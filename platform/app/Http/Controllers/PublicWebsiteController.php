<?php
namespace App\Http\Controllers;
use App\Models\Media;
use App\Models\SourceRecord;
use App\Services\PublicContent;
use Illuminate\Http\Request;

class PublicWebsiteController extends Controller
{
    private function shared(): array
    {
        return ['siteName'=>app(\App\Services\Settings::class)->get('site_name',config('platform.brand')),
            'description'=>__('public.hero_intro'),'heroImage'=>config('public_ui.hero_image')];
    }
    public function home(PublicContent $content)
    {
        $videos=$content->query()->whereIn('kind',['video','short'])->latest()->limit(3)->get()->map($content->card(...));
        $articles=$content->query()->where('kind','post')->latest()->limit(3)->get()->map($content->card(...));
        return view('public.home',[...$this->shared(),'videos'=>$videos,'articles'=>$articles,'featured'=>$videos->first(),'book'=>null,'live'=>null]);
    }
    public function listing(Request $request,PublicContent $content)
    {
        $data=$request->validate(['q'=>'nullable|string|max:120']);
        $section=$request->route('section');$query=$content->query();
        if($section==='videos')$query->whereIn('kind',['video','short']);
        elseif($section==='beitraege')$query->where('kind','post');
        elseif($section!=='search')$query->whereRaw('1=0');
        if($data['q']??'')$query->where(fn($q)=>$q->where('title','like','%'.$data['q'].'%')->orWhere('body','like','%'.$data['q'].'%'));
        $page=$query->latest()->paginate(12)->withQueryString();
        $settings=app(\App\Services\Settings::class);
        $key=['impressum'=>'impressum','datenschutz'=>'privacy_policy'][$section]??null;
        $document=$key?($settings->get('legal_documents',[])[app()->getLocale()][$key]??''):'';
        return view('public.section',[...$this->shared(),'section'=>$section,'items'=>$page->through($content->card(...)),
            'document'=>trim(strip_tags(str_replace(['</p>','<br>','<br/>','</li>'],"\n",$document))), 'contactEmail'=>$settings->get('contact_email','')]);
    }
    public function detail(Request $request,string $slug,PublicContent $content)
    {
        abort_unless(preg_match('/-(\d+)$/',$slug,$matches),404);
        $section=$request->route('section');
        $record=$content->query()->whereIn('kind',$section==='videos'?['video','short']:['post'])->findOrFail($matches[1]);
        $card=$content->card($record);if($request->url()!==$card['url'])return redirect($card['url'],301);
        return view('public.detail',[...$this->shared(),'section'=>$section,'record'=>$record,'card'=>$card,'assets'=>$content->assets($record)]);
    }
    public function media(SourceRecord $record,Media $media,PublicContent $content)
    {
        abort_unless($content->query()->whereKey($record->id)->exists()&&$content->assets($record)->contains('id',$media->id),404);
        return app(MediaController::class)->preview($media);
    }
}
