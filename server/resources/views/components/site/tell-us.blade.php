{{-- A 404's way to tell us (docs/feedback-plan.md): the app's Contact us, opened from here with the missing page as
     text (its path only, never its query). --}}
<p class="s-notice-tell">{{ __('errors.tell.lead') }} <a href="{{ \App\Support\Http\Hosts::app('/contact?'.http_build_query(['from' => 'site-404', 'page' => mb_substr(request()->getPathInfo(), 0, 300)], '', '&', PHP_QUERY_RFC3986)) }}" data-tell-us>{{ __('errors.tell.link') }}</a>.</p>
