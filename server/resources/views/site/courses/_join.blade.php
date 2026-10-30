{{-- Joining a course: Plus, with the first lesson free and no account needed to try it (saving your
     progress takes a free account); Coming soon until the course opens. The app opens a Plus course at
     /learn/c/<its catalogue id> (the page's `app`). --}}
@php($tryUrl = \App\Support\Http\Hosts::app('/learn/c/'.($page['app'] ?? $page['slug'])))
<div class="cp-join-card">
    <p class="cp-join-line">{{ $open ? __('pages.site.course_join_open') : __('pages.site.course_soon') }}</p>
    @if ($open)
        <p class="cp-join-note">{{ __('pages.site.course_join_note') }}</p>
    @endif
    <p class="cp-join-btns">
        @if ($open)
            <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
            <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
        @else
            <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
        @endif
    </p>
</div>
