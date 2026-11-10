{{-- Free courses and free tools on the landing (Hillel, 2026-11-07: "make the tools and courses pages more reachable
     from the main page"): after how it works, never above the call to action. Landing v2 (docs/landing-v2-plan.md): the
     beginner course as a dark Start here card, its title to the course's page and Try lesson 1 into the app's first
     lesson, then the courses Landing::FEATURED_COURSES names three by two, as the "for" pages draw courses; under a
     hairline, the tools people reach for most beside their heading, lighter than a section of their own. Both lead to
     the whole list. Both skins dress the same cards. --}}
@php($courses = \App\Support\Pages\Landing::featuredCourses())
@php($tools = \App\Support\Pages\Landing::featuredTools())
<section class="s-wrap s-sec" aria-labelledby="free-courses-h" data-landing-courses>
    <div class="s-headrow">
        <div>
            <p class="s-kick">{{ __('pages.landing.free_courses_kick') }}</p>
            <h2 id="free-courses-h" class="s-title">{{ __('pages.landing.free_courses_title') }}</h2>
            <p class="s-lede">{{ __('pages.landing.free_courses_lead') }}</p>
        </div>
        <a href="/courses" class="s-arrow">{{ __('pages.site.all_courses') }} <x-site.icon name="arrow" size="16" /></a>
    </div>
    <div class="s-startcard s-ink">
        <div>
            <p class="s-kick">{{ __('pages.landing.start_here') }}</p>
            <h3><a href="/features/course">{{ __('site.courses.index.free_name') }}</a></h3>
            <p>{{ __('site.courses.index.free_summary') }}</p>
        </div>
        <a href="{{ $links['start'] }}" class="s-startcard-go" data-funnel-start>{{ __('pages.landing.try_lesson') }} <x-site.icon name="arrow" size="16" /></a>
    </div>
    <ul class="s-cards s-coursecards">
        @foreach ($courses as $slug)
            <li>
                <a href="/courses/{{ $slug }}" class="s-card">
                    <b>{{ __('site.courses.pages.'.$slug.'.name') }}</b>
                    <span>{{ __('site.courses.pages.'.$slug.'.summary') }}</span>
                </a>
            </li>
        @endforeach
    </ul>
    @if ($tools !== [])
        <div class="s-toolsrow" aria-labelledby="free-tools-h" role="region" data-landing-tools>
            <div>
                <p class="s-kick">{{ __('pages.landing.free_tools_kick') }}</p>
                <h2 id="free-tools-h" class="s-title">{{ __('pages.landing.free_tools_title') }}</h2>
                <p class="s-lede">{{ __('pages.landing.free_tools_lead') }}</p>
            </div>
            <div class="s-toolsrow-chips">
                <ul class="s-chips">
                    @foreach ($tools as $tool)
                        <li><a href="{{ $tool['url'] }}"><span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon :name="$tool['icon']" size="16" /></span>{{ $tool['name'] }}</a></li>
                    @endforeach
                </ul>
                <a href="{{ \App\Support\Site\SitePages::toolsHome() }}" class="s-arrow">{{ __('pages.landing.free_tools_all') }} <x-site.icon name="arrow" size="16" /></a>
            </div>
        </div>
    @endif
</section>
