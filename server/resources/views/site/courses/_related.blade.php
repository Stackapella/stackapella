{{-- Courses that go well with this one (the page's `related`), and back to all of them. --}}
<ul class="cp-related">
    @foreach ((array) ($page['related'] ?? []) as $slug)
        <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }} <x-site.icon name="arrow" size="16" /></a></li>
    @endforeach
</ul>
<p class="cp-related-all"><a href="/courses" class="s-arrow">{{ __('pages.site.course_all') }} <x-site.icon name="arrow" size="16" /></a></p>
