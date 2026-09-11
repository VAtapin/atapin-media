@if(config('platform.theme')==='manna')
<span class="kit-brand {{ ($mark ?? false) ? 'kit-brand-mark' : (($inverse ?? false) ? 'kit-brand-inverse' : '') }}" role="img" aria-label="{{ config('platform.brand') }}">
    <img src="/assets/brand/approved-ui-kit.png" width="1672" height="941" alt="" aria-hidden="true">
</span>
@else
<span>{{ config('platform.brand') }}</span>
@endif
