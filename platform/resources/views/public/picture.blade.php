@if(isset($art))
@php
    $w=$art['w']; $h=$art['h'];
    $source=$art['source']??'approved-start.png';
@endphp
<span class="public-source-picture {{ $pictureClass??'' }}" style="aspect-ratio:{{ $w }}/{{ $h }}" @if(!empty($alt))role="img" aria-label="{{ $alt }}"@else aria-hidden="true"@endif><img src="/assets/brand/{{ $source }}" alt="" style="width:{{ 1672/$w*100 }}%;height:{{ 941/$h*100 }}%;left:{{ -$art['x']/$w*100 }}%;top:{{ -$art['y']/$h*100 }}%"></span>
@elseif(!empty($image))
<img class="public-photo {{ $pictureClass??'' }}" src="{{ $image }}" alt="{{ $alt??'' }}" loading="lazy">
@endif
