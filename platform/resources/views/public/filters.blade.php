<div class="public-filters" id="catalog">
<nav aria-label="{{ __('public.topics') }}">
<a href="{{ route('public.'.$section) }}" @class(['current'=>!request('tag')&&!request('series')])>{{ __('public.all_'.$section) }} <small>({{ $items->total() }})</small>
</a>
@foreach(__('public.filters_'.$section) as $label)<a href="{{ route('public.'.$section,['tag'=>$label]) }}" @class(['current'=>request('tag')===$label])>{{ $label }}</a>
@endforeach</nav>
<form class="public-catalog-search" method="get">
<label class="public-sr-only" for="catalog-q">{{ __('ui.search') }}</label>
<input id="catalog-q" type="search" name="q" maxlength="120" value="{{ request('q') }}" placeholder="{{ __('ui.search') }}">
@if(request('tag'))<input type="hidden" name="tag" value="{{ request('tag') }}">
@endif
@if(request('series'))<input type="hidden" name="series" value="{{ request('series') }}">
@endif<select name="sort" aria-label="{{ __('public.sort') }}" data-submit-select>
@foreach(['latest','popular','oldest'] as $sort)<option value="{{ $sort }}" @selected(request('sort','latest')===$sort)>{{ __('public.sort_'.$sort) }}</option>
@endforeach</select>
<button class="public-button">{{ __('ui.search') }}</button>
</form>
</div>
