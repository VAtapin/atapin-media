<?php
namespace App\Http\Controllers;
use App\Models\{SourceRecord,Media};
use App\Services\{PublicContent,PublicCatalog,Settings};
use Illuminate\Http\Request;
class ContentPreviewController extends Controller
{
    public function show(Request $request,SourceRecord $record,PublicContent $content,PublicCatalog $catalog)
    {
        abort_unless(in_array($record->kind,['video','short','post']),404);
        $section=$record->kind==='post'?'beitraege':'videos';
        $data=$catalog->detail($request,$record,$section);
        $cover=$data['assets']->firstWhere('id',$record->metadata['cover_media_id']??'')??$data['assets']->firstWhere('kind','image');
        $data['card']['image']=$cover?route('content.preview-media',[$record,$cover]):null;
        return response()->view('public.'.($section==='videos'?'video':'beitrag'),[...$data,'preview'=>true,'section'=>$section,'layoutMode'=>'detail',
            'siteName'=>app(Settings::class)->get('site_name',config('platform.brand')),'description'=>__('public.hero_intro'),'heroImage'=>config('public_ui.hero_image')])
            ->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex, nofollow');
    }
    public function media(SourceRecord $record,Media $media,PublicContent $content)
    {
        abort_unless($content->assets($record)->contains('id',$media->id),404);
        $response=app(MediaController::class)->preview($media);$response->headers->set('Cache-Control','private, no-store');return $response;
    }
}
