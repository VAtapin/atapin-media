@extends('layouts.app')
@section('title', __('ui.settings'))
@section('content')
<div data-settings-app data-active-section="{{ old('section', 'desktop_design') }}">@include('settings-content')</div>
<script src="/assets/settings-tabs.js" defer></script>
@endsection