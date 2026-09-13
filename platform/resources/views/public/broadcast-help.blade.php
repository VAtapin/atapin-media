<button class="public-button public-button-secondary" type="button" data-public-help="broadcast-help" aria-controls="broadcast-help" aria-label="{{ __('public.broadcast_help_title') }}">? {{ __('public.broadcast_help_title') }}</button>
<dialog id="broadcast-help" class="public-help-dialog" aria-labelledby="broadcast-help-title">
<form method="dialog"><button class="public-button public-button-secondary" autofocus>{{ __('public.broadcast_help_close') }}</button></form>
<h2 id="broadcast-help-title">{{ __('public.broadcast_help_title') }}</h2>
<h3>{{ __('public.broadcast_help_streamer') }}</h3>
<ol>@foreach(['event','tunnel','obs','finish'] as $step)<li><p>{{ __('public.broadcast_help_'.$step) }}</p>@if($step==='tunnel')<pre><code>ssh -N -L 1935:127.0.0.1:1935 SSH_BENUTZER&#64;{{ parse_url(config('app.url'),PHP_URL_HOST) }}</code></pre>@endif @endforeach</ol>
<h3>{{ __('public.broadcast_help_admin') }}</h3>
<p>{{ __('public.broadcast_help_server') }}</p>
<pre><code>cd {{ dirname(base_path()) }}
/opt/plesk/php/8.4/bin/php platform/artisan public:live-config
bash platform/bin/live-server.sh install
bash platform/bin/live-server.sh start
bash platform/bin/live-server.sh status</code></pre>
<p>{{ __('public.broadcast_help_nginx_where') }}</p>
<pre><code>location /_live/ {
    proxy_pass http://127.0.0.1:8888/;
    proxy_http_version 1.1;
    proxy_buffering off;
}</code></pre>
<p>{{ __('public.broadcast_help_nginx_why') }}</p>
<p>{{ __('public.broadcast_help_diagnostics') }}</p>
<h3>Composer</h3><p>{{ __('public.broadcast_help_composer') }}</p>
</dialog>
