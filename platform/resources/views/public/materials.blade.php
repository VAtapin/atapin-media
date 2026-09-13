<div class="public-materials">
@forelse($assets as $asset)<a href="{{ route('public.media',[$record,$asset]) }}" download="{{ $asset->original_name }}">
@include('public.icon',['name'=>'article']) {{ $asset->title }} <small>{{ $asset->formattedSize() }}</small> ↓</a>
@empty
@include('public.empty')
@endforelse</div>
