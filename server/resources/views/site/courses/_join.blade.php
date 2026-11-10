{{-- Joining a course: free, every lesson, with no account needed to start (saving your progress
     takes a free account; Hillel, 2026-11-06); Coming soon until the course opens. The button opens the course's
     first lesson in the app, tagged (the controller's $courseStart). --}}
@php($tryUrl = $courseStart)
<div class="cp-join-card">
    <p class="cp-join-line">{{ $open ? __('pages.site.course_join_open') : __('pages.site.course_soon') }}</p>
    @if ($open)
        <p class="cp-join-note">{{ __('pages.site.course_join_note') }}</p>
    @endif
    <p class="cp-join-btns">
        @if ($open)
            <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
        @else
            <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
        @endif
    </p>
</div>
