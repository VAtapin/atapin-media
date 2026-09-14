<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class TrackPublicAnalytics
{
    public function handle(Request $request,Closure $next)
    {
        $response=$next($request);$name=$request->route()?->getName()??'';
        if($request->isMethod('get')&&$response->getStatusCode()===200&&($name==='home'||in_array($name,['public.videos','public.beitraege','public.buecher','public.live','public.podcast','public.community','public.video','public.article','public.book','public.suche','public.ueber-uns','public.unsere-mission','public.kontakt'],true))){
            app(\App\Services\DesktopAnalytics::class)->track($request,'page_view','/'.$request->path());
            if($name==='public.suche'&&$request->filled('q'))app(\App\Services\DesktopAnalytics::class)->track($request,'search','search');
        }
        if($request->isMethod('get')&&$response->getStatusCode()===200&&in_array($name,['public.book-media','public.media','public.purchased-pdf'],true)&&($name==='public.purchased-pdf'||str_contains((string)$response->headers->get('Content-Type'),'application/pdf')))app(\App\Services\DesktopAnalytics::class)->track($request,'pdf_access',$name);
        return $response;
    }
}
