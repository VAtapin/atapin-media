@extends('public.layout')
@section('content')<section class="public-panel"><h1>{{ __('public.newsletter_cancel') }}</h1><form method="post" action="{{ request()->fullUrl() }}">@csrf<button class="public-button">{{ __('public.newsletter_cancel') }}</button></form></section>@endsection
