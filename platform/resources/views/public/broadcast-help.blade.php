<button class="public-button public-button-secondary" type="button" data-public-help="broadcast-help" aria-controls="broadcast-help" aria-label="{{ __('public.broadcast_help_title') }}">? {{ __('public.broadcast_help_title') }}</button>
<dialog id="broadcast-help" class="public-help-dialog" aria-labelledby="broadcast-help-title">
<form method="dialog"><button class="public-button public-button-secondary" autofocus>{{ __('public.broadcast_help_close') }}</button></form>
<h2 id="broadcast-help-title">{{ __('public.broadcast_help_title') }}</h2>
<h3>{{ __('public.broadcast_help_streamer') }}</h3>
<ol>@foreach(['event','obs','finish'] as $step)<li><p>{{ __('public.broadcast_help_'.$step) }}</p></li>@endforeach</ol>
<h3>{{ __('public.broadcast_help_admin') }}</h3>
<p>{{ __('public.broadcast_help_server') }}</p>
<p>{{ __('public.broadcast_help_rtmps_cert') }}</p>
<p>{{ __('public.broadcast_help_diagnostics') }}</p>
</dialog>
