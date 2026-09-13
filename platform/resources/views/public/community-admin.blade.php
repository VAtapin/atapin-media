@extends('public.layout')
@section('content')<section class="public-panel"><h1>{{ __('public.community_moderation') }}</h1>
@forelse($entries as $entry)<article><h2>{{ $entry->title }}</h2><p>{{ $entry->metadata['author']??'—' }} · {{ $entry->created_at->format('d.m.Y H:i') }}</p><div class="public-document">{{ $entry->body }}</div><form method="post" action="{{ route('public.community-moderate',$entry) }}">@csrf @method('PATCH')<button name="decision" value="publish">{{ __('public.broadcast_publish') }}</button><button name="decision" value="reject">{{ __('public.review_reject') }}</button></form></article>@empty @include('public.empty') @endforelse{{ $entries->links() }}
</section>@endsection
