@forelse($comments as $comment)
@php($commentOwn=app(\App\Services\PublicContent::class)->isOwnedByViewer($comment,auth()->user(),request()->hasSession()?request()->session()->getId():null))
@php($commentModeration=$comment->metadata['moderation']??[])
<article @class(['public-chat-message','is-blocked'=>$commentOwn&&($commentModeration['blocked']??false)]) data-comment-id="{{ $comment->id }}">
    <span class="public-avatar">{{ mb_substr(is_string($comment->metadata['author']??null)?$comment->metadata['author']:'',0,1)?:'◇' }}</span>
    <div><strong>{{ is_string($comment->metadata['author']??null)?$comment->metadata['author']:'—' }}</strong><small>{{ $comment->created_at->format('d.m.Y') }}</small><p>{{ $comment->body }}</p>
    @if($commentOwn&&($commentModeration['blocked']??false))<span class="public-chat-status public-chat-status-blocked">{{ __('public.chat_blocked') }}</span>
    @elseif($commentOwn&&($commentModeration['state']??null)==='pending_ai')<span class="public-chat-status">{{ __('public.chat_moderation_pending') }}</span>@endif</div>
</article>
@empty
@include('public.empty')
@endforelse
@if(method_exists($comments,'hasPages'))@include('public.pagination',['items'=>$comments])@endif
