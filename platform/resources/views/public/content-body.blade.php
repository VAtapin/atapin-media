@if($record && ($record->metadata['body_format']??'plain')==='html'){!! app(\App\Services\RichContent::class)->sanitize($record->body??'') !!}@else{{ $record?->body??__('public.no_data') }}@endif
