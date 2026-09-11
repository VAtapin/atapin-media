@if(config('platform.theme')==='manna')
@php
    $brandMark = ($mark ?? false) || ($inverse ?? false);
    $brandVariant = $brandMark ? 'mark' : (($horizontal ?? false) ? 'horizontal' : 'stacked');
@endphp
<span class="kit-brand kit-brand-{{ $brandVariant }}" role="img" aria-label="{{ config('platform.brand') }}">
    <img src="/assets/brand/owner/logo-{{ $brandVariant }}.png" alt="" aria-hidden="true">
</span>
@else
<span>{{ config('platform.brand') }}</span>
@endif
