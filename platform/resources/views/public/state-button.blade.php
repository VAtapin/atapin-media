@if($subject)<form action="{{ route($subject instanceof \App\Models\Product?'public.book-state':'public.record-state',$subject) }}" method="post" data-public-form>@csrf<input type="hidden" name="action" value="{{ $action }}">
@if($action==='progress')<input type="hidden" name="position" value="100">
@else<input type="hidden" name="enabled" value="{{ ($states[$action]['enabled']??false)?'0':'1' }}">
@endif<button @disabled($action==='reminder'&&!($states[$action]['enabled']??false)&&(!app(\App\Services\PublicLiveReminders::class)->mailReady()||!app(\App\Services\PublicLiveReminders::class)->time($subject)?->isFuture())) @class(['public-button','public-button-secondary','public-live-reminder-button'=>$action==='reminder','current'=>($states[$action]['enabled']??false)])>{{ $label }}
@if($states[$action]['enabled']??false) ✓
@endif</button>
</form>
@else<button class="public-button public-button-secondary" disabled title="{{ __('public.no_data') }}">{{ $label }}</button>
@endif
