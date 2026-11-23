{{-- The courses that fit an audience page (site.for.pages.<who>.courses), drawn as the courses page draws them: the
     "for" pages and For choir directors. --}}
@php($fit = collect($page['courses'] ?? [])->intersect(\App\Support\Site\SitePages::courses())->values()->all())
@if ($fit !== [])
    <section class="s-wrap s-sec" aria-labelledby="fit-h">
        <div class="s-headrow">
            <h2 id="fit-h" class="s-kick">{{ __('pages.site.fit_courses') }}</h2>
            <a href="/courses" class="s-arrow">{{ __('pages.site.all_courses') }} <x-site.icon name="arrow" size="16" /></a>
        </div>
        <ul class="s-cards s-coursecards">
            @foreach ($fit as $slug)
                <li class="s-neon{{ $loop->index % 3 + 1 }}">
                    <a href="/courses/{{ $slug }}" class="s-card">
                        <b>{{ __('site.courses.pages.'.$slug.'.name') }}@if (\App\Support\Site\SitePages::courseOpen($slug)) <span class="s-soon s-open">{{ __('pages.site.course_open') }}</span>@endif</b>
                        <span>{{ __('site.courses.pages.'.$slug.'.summary') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
