@extends('layouts.app')
@section('title', __('ui.settings'))
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ config('platform.brand') }}</p><h1>{{ __('ui.settings') }}</h1></div></div><section class="panel form-panel"><h2>{{ __('ui.website') }}</h2><form method="post" action="{{ route('settings') }}">@csrf @method('PUT')
<label>{{ __('ui.site_name') }}<input name="site_name" required maxlength="120" value="{{ old('site_name',$settings['site_name'] ?? config('platform.brand')) }}"></label>
<label>{{ __('ui.site_description') }}<textarea name="site_description" rows="4" maxlength="500">{{ old('site_description',$settings['site_description'] ?? '') }}</textarea></label>
<label>{{ __('ui.contact_email') }}<input name="contact_email" type="email" value="{{ old('contact_email',$settings['contact_email'] ?? '') }}"></label>
<h2>{{ __('ui.desktop_appearance') }}</h2>
<p class="muted">{{ __('ui.desktop_appearance_hint') }}</p>
<div class="form-row">
<label>{{ __('ui.icon_set') }}<select name="desktop_icon_set">@foreach(config('desktop.icon_sets') as $key => $set)<option value="{{ $key }}" @selected(old('desktop_icon_set',$settings['desktop_icon_set'] ?? 'manna') === $key)>{{ __('ui.'.$set['label_key']) }}</option>@endforeach</select></label>
<label>{{ __('ui.wallpaper') }}<select name="desktop_wallpaper">@foreach(config('desktop.wallpapers') as $key => $wallpaper)<option value="{{ $key }}" @selected(old('desktop_wallpaper',$settings['desktop_wallpaper'] ?? 'mountains') === $key)>{{ __('ui.'.$wallpaper['label_key']) }}</option>@endforeach</select></label>
<label>{{ __('ui.accent_color') }}<select name="desktop_accent">@foreach(config('desktop.accents') as $key => $accent)<option value="{{ $key }}" @selected(old('desktop_accent',$settings['desktop_accent'] ?? 'gold') === $key)>{{ __('ui.'.$accent['label_key']) }}</option>@endforeach</select></label>
</div><button class="button">{{ __('ui.save') }}</button></form></section>
@endsection
