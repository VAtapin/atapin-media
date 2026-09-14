<section class="public-comments" id="comments" @if($record) data-comments-url="{{ route('public.comments',$record) }}" @endif>
<h2>{{ __('public.comments') }} (<span data-comment-count>{{ method_exists($comments,'total')?$comments->total():$comments->count() }}</span>)</h2>
<div data-comment-list>@include('public.comment-list')</div>
@if($record&&($communityBlocked??false))<div class="public-chat-block-notice" role="alert">{{ __('public.chat_blocked_three') }}</div>@elseif($record)<form action="{{ route('public.message-submit',$record) }}" method="post" data-public-ajax="message">@csrf
<label for="comment-body">{{ __('public.write_comment') }}</label>
<textarea id="comment-body" name="body" required minlength="2" maxlength="5000" rows="3">
</textarea>
<small>{{ __('public.chat_guest_hint') }}</small>
<button type="submit" class="public-button public-chat-send">{{ __('public.send') }}</button>
<p data-public-form-message class="public-chat-feedback" role="status" hidden></p>
</form>
@else<button class="public-button" disabled>{{ __('public.write_comment') }}</button>
@endif</section>
