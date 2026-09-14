@extends('public.layout',['title'=>$bookRecord?->title??__('public.no_data'),'layoutMode'=>'detail'])

@section('content')
@php($card=$card??['image'=>null,'author'=>'','price'=>null,'excerpt'=>__('public.no_data')])
@php($canBuyPdf=$bookRecord&&$bookRecord->price_cents>0&&app(\App\Services\StripePayments::class)->ready()&&app(\App\Services\StripePayments::class)->edition($bookRecord))
<div class="public-book-detail public-detail-layout">
<div>
<nav class="public-breadcrumb">
<a href="/">{{ __('public.nav_start') }}</a> › <a href="/buecher">{{ __('public.nav_buecher') }}</a> › {{ $bookRecord?->title??__('public.no_data') }}</nav>
<div class="public-book-intro">
<div class="public-book-cover">
@include('public.picture',['image'=>$card['image']??null,'icon'=>'book'])</div>
<div>
<p class="public-eyebrow">{{ __('public.book') }}</p>
<h1>{{ $bookRecord?->title??'—' }}</h1>
<p class="public-article-intro">{{ $card['excerpt']??__('public.no_data') }}</p>
<p class="public-record-meta">◇ {{ __('public.author') }}: {{ $card['author']?:__('public.no_data') }}</p>
<div class="public-book-formats">
@foreach(['book','pdf','ebook'] as $format)<span>
@include('public.icon',['name'=>'book']) {{ __('public.'.$format) }}</span>
@endforeach</div>
<p class="public-muted">{{ __('public.reviews') }}: {{ $reviews->count()?:__('public.no_data') }}</p>
@if($bookRecord?->isbn)<p>ISBN: {{ $bookRecord->isbn }}</p>@endif
@if($bookRecord?->page_count)<p>{{ __('public.book_field_page_count') }}: {{ $bookRecord->page_count }}</p>@endif
</div>
</div>
<section class="public-panel public-tabset" data-public-tabs>
<div class="public-tabs" role="tablist" aria-label="{{ __('public.content') }}">
@foreach(['description','contents','reviews','related_materials'] as $tab)<button id="tab-{{ $tab }}" role="tab" aria-selected="{{ $loop->first?'true':'false' }}" aria-controls="panel-{{ $tab }}" tabindex="{{ $loop->first?'0':'-1' }}">{{ __('public.'.$tab) }}</button>
@endforeach</div>
<div id="panel-description" role="tabpanel" aria-labelledby="tab-description">
<h2>{{ __('public.description') }}</h2>
<div class="public-document">{{ $bookRecord?->description??__('public.no_data') }}</div>
</div>
<div id="panel-contents" role="tabpanel" aria-labelledby="tab-contents" hidden>
@if($bookRecord?->contents)<div class="public-document">{{ $bookRecord->contents }}</div>@else @include('public.empty') @endif</div>
<div id="panel-reviews" role="tabpanel" aria-labelledby="tab-reviews" hidden>
@forelse($reviews as $review)<article><strong>{{ $review->user?->name }} · {{ $review->rating }}/5</strong><p>{{ $review->body }}</p></article>@empty @include('public.empty') @endforelse
@if($reviews instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator){{ $reviews->links() }}@endif
@if($bookRecord)@auth<form method="post" action="{{ route('public.book-review',$bookRecord) }}" data-public-ajax="review">@csrf<label>{{ __('public.review_rating') }}<select name="rating">@foreach(range(5,1) as $rating)<option>{{ $rating }}</option>@endforeach</select></label><label>{{ __('public.reviews') }}<textarea name="body" required maxlength="3000"></textarea></label><button class="public-button">{{ __('public.review_submit') }}</button></form>@else<a href="/login">{{ __('ui.login') }}</a>@endauth @endif</div>
<div id="panel-related_materials" role="tabpanel" aria-labelledby="tab-related_materials" hidden>
@foreach($assets as $asset)<p>
<a href="{{ route('public.book-media',[$bookRecord,$asset]) }}">{{ $asset->title }} →</a>
</p>
@endforeach
@if($assets->isEmpty())
@include('public.empty')
@endif</div>
</section>
</div>
<aside class="public-right-sidebar">
<section class="public-panel public-purchase">
<h2>{{ $card['price']??'—' }}</h2>
<p class="public-muted">{{ $canBuyPdf?__('workspaces.payment_confirmation'):__('public.purchase_hint') }}</p>
@if($bookRecord)
@auth
@if(app(\App\Services\StripePayments::class)->entitled($bookRecord,auth()->user()))<a class="public-button" href="{{ route('public.purchased-pdf',$bookRecord) }}">{{ __('workspaces.owned') }}</a>
@elseif($canBuyPdf)<form method="post" action="{{ route('public.checkout',$bookRecord) }}">@csrf<button class="public-button">{{ __('workspaces.buy') }}</button></form>@endif
@else @if($canBuyPdf)<a class="public-button" href="/login">{{ __('workspaces.buy') }}</a>@endif @endauth
@if($bookRecord->metadata['external_shop_url']??null)<a class="public-button" href="{{ $bookRecord->metadata['external_shop_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('workspaces.external_shop_url') }}</a>@endif
@endif
@if($bookRecord)<a class="public-button" href="{{ route('public.kontakt',['book'=>$bookRecord->id]) }}">{{ __('public.purchase_request') }} →</a>
@else<button class="public-button" disabled>{{ __('public.purchase_request') }}</button>
@endif
@if($pdf=$assets->firstWhere('mime','application/pdf'))<a class="public-button public-button-secondary" href="{{ route('public.book-media',[$bookRecord,$pdf]) }}" download>{{ __('public.pdf_download') }} ↓</a>
<a class="public-button public-button-secondary" href="{{ route('public.book-media',[$bookRecord,$pdf]) }}" target="_blank" rel="noopener">{{ __('public.read_sample') }} →</a>
@else<button class="public-button public-button-secondary" disabled>{{ __('public.pdf_download') }}</button>
<button class="public-button public-button-secondary" disabled>{{ __('public.read_sample') }}</button>
@endif<div class="public-action-row">
@include('public.state-button',['subject'=>$bookRecord,'action'=>'bookmark','label'=>__('public.bookmark')])
@include('public.state-button',['subject'=>$bookRecord,'action'=>'progress','label'=>__('public.mark_read')])</div>
<p id="reading">{{ __('public.reading_progress') }}:
@if(isset($states['progress'])){{ min(100,$states['progress']['position']) }}%
@else◇ {{ __('public.no_data') }}
@endif</p>
</section>
@include('public.cards',['heading'=>__('public.related_video'),'cards'=>$relatedVideo?[$relatedVideo]:[],'panelClass'=>'public-related','listingUrl'=>'/videos'])
@include('public.cards',['heading'=>__('public.related_articles'),'cards'=>$relatedArticle?[$relatedArticle]:[],'panelClass'=>'public-related','listingUrl'=>'/beitraege'])
@include('public.cards',['heading'=>__('public.related_books'),'cards'=>$relatedBooks,'style'=>'book','panelClass'=>'public-small-books','listingUrl'=>'/buecher'])</aside>
</div>

@endsection
