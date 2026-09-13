<section class="public-comments" id="comments">
<h2>{{ __('public.comments') }} ({{ method_exists($comments,'total')?$comments->total():$comments->count() }})</h2>
@forelse($comments as $comment)<article>
<span class="public-avatar">{{ mb_substr(is_string($comment->metadata['author']??null)?$comment->metadata['author']:'',0,1)?:'◇' }}</span>
<div>
<strong>{{ is_string($comment->metadata['author']??null)?$comment->metadata['author']:'—' }}</strong>
<small>{{ $comment->created_at->format('d.m.Y') }}</small>
<p>{{ $comment->body }}</p>
</div>
</article>
@empty
@include('public.empty')
@endforelse
@if(method_exists($comments,'hasPages'))@include('public.pagination',['items'=>$comments])@endif
@if($record)<form action="{{ route('public.message-submit',$record) }}" method="post">@csrf
<label for="comment-body">{{ __('public.write_comment') }}</label>
<textarea id="comment-body" name="body" required minlength="2" maxlength="5000" rows="3">
</textarea>
<small>{{ __('public.chat_guest_hint') }}</small>
<button class="public-button">{{ __('public.send') }} →</button>
</form>
@else<button class="public-button" disabled>{{ __('public.write_comment') }}</button>
@endif</section>
