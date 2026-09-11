@extends('layouts.app')
@section('title', __('ui.settings'))
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ config('platform.brand') }}</p><h1>{{ __('ui.settings') }}</h1></div></div><section class="panel form-panel"><h2>{{ __('ui.website') }}</h2><form method="post" action="{{ route('settings') }}">@csrf @method('PUT')
<label>{{ __('ui.site_name') }}<input name="site_name" required maxlength="120" value="{{ old('site_name',$settings['site_name'] ?? config('platform.brand')) }}"></label>
<label>{{ __('ui.site_description') }}<textarea name="site_description" rows="4" maxlength="500">{{ old('site_description',$settings['site_description'] ?? '') }}</textarea></label>
<label>{{ __('ui.contact_email') }}<input name="contact_email" type="email" value="{{ old('contact_email',$settings['contact_email'] ?? '') }}"></label><button class="button">{{ __('ui.save') }}</button></form></section>
@endsection
