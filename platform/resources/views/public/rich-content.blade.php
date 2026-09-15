@php($rendered = app(\App\Services\RichContent::class)->render($content ?? ''))
@if($rendered !== ''){!! $rendered !!}@elseif(isset($empty)){{ $empty }}@endif
