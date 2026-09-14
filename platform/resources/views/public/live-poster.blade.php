<div class="public-live-poster-copy">
@if($liveStatusLabel)<span class="public-live-poster-status" data-live-status-line><span class="public-live-status-dot status-{{ $liveStatus }}" data-live-status-dot aria-hidden="true"></span><span data-live-status-label>{{ $liveStatusLabel }}</span></span>@endif
<strong>{{ $record?->title??__('public.next_live') }}</strong>
@if($liveDate)<time datetime="{{ $record?->metadata['starts_at'] }}">{{ $liveDate }}</time>@endif
@if($record?->body)<span class="public-live-poster-description">{{ \Illuminate\Support\Str::limit($record->body,180) }}</span>@endif
</div>
