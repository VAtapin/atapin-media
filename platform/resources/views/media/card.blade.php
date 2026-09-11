<a class="media-card" href="{{ route('media.show',$item) }}"><div class="media-cover">
@if(in_array($item->mime,['image/jpeg','image/png','image/webp','image/gif']))<img loading="lazy" src="{{ route('media.preview',$item) }}" alt="">@else<span class="file-symbol">@include('components.icon',['name'=>'media'])</span>@endif
<span class="badge">{{ __('ui.kind_'.$item->kind) }}</span></div><div class="media-copy"><h3>{{ $item->title }}</h3><p>{{ $item->formattedSize() }} <span>·</span> {{ $item->created_at->format('d.m.Y') }}</p></div></a>
