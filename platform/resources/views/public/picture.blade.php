@if(!empty($image))
<img class="public-photo {{ $pictureClass??'' }}" src="{{ $image }}" alt="{{ $alt??'' }}" loading="lazy">
@else
<span class="public-photo public-photo-placeholder {{ $pictureClass??'' }}" aria-hidden="true">@include('public.icon',['name'=>($icon??'video')==='video'?'film':$icon])</span>
@endif
