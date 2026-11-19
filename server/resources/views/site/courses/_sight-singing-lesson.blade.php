{{-- A lesson as a bar on the Sight-singing page's lessons (site/courses/sight-singing): its number, "Start here" on the
     first, its name and its page's line. --}}
<span class="cp-ss-lesson-top"><span class="cp-ss-lesson-n">{{ $lesson['number'] }}</span>@if ($start)<span class="cp-ss-free">{{ __('pages.site.course_free') }}</span>@endif</span>
<b>{{ $lesson['name'] }}</b>
@if ($lesson['line'] !== '')
    <span class="cp-ss-lesson-line">{{ $lesson['line'] }}</span>
@endif
