<div class="public-materials">
@forelse($assets->reject(fn($asset)=>$asset->kind==='video') as $asset)<a href="{{ $asset->publicUrl() ?? route('public.media',[$record,$asset]) }}" download="{{ $asset->original_name }}">
@include('public.icon',['name'=>'article']) {{ $asset->title }} <small>{{ $asset->formattedSize() }}</small> ↓</a>
@empty
@include('public.empty')
@endforelse</div>
