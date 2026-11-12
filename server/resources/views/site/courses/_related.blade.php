{{-- Courses that go well with this one (the page's `related`), the guides that go with it (its `guides`), and back to all of them. --}}
<ul class="cp-related">
    @foreach ((array) ($page['related'] ?? []) as $slug)
        <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }} <x-site.icon name="arrow" size="16" /></a></li>
    @endforeach
    @foreach ((array) ($page['guides'] ?? []) as $guideSlug)
        @php($guideDoc = app(\App\Support\Legal\LegalText::class)->read($guideSlug, app()->getLocale(), folder: 'guides'))
        <li><a href="/guides/{{ $guideSlug }}">{{ $guideDoc['title'] }} <x-site.icon name="arrow" size="16" /></a></li>
    @endforeach
</ul>
<p class="cp-related-all"><a href="/courses" class="s-arrow">{{ __('pages.site.course_all') }} <x-site.icon name="arrow" size="16" /></a></p>
