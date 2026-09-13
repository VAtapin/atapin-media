<section class="public-panel {{ $panelClass??'' }}">
<div class="public-panel-heading">
<h2>{{ $heading }}</h2>
@if(!empty($listingUrl))<a href="{{ $listingUrl }}">{{ __('public.show_all') }} →</a>
@endif</div>
<div class="public-cards public-cards-{{ $style??'video' }}">
@forelse($cards as $item)
@include('public.card')
@empty
@include('public.empty',['url'=>$emptyUrl??null])
@endforelse</div>
@if(isset($paginator))
@include('public.pagination',['items'=>$paginator])
@endif</section>
