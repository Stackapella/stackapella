{{-- The courses coming to Plus (/courses; docs/plus-courses-plan.md; Hillel, 2026-10-29: "All 27 now"):
     each group's courses as cards to their own pages, all Coming soon, then the free course that works
     today. Nexus only. Words: lang/en/site.php `courses`. --}}
@extends('public.layout', ['title' => $meta['title']])

@section('head')
    @include('pages._head')
@endsection

{{-- Whether any course is open yet: the head says Coming soon only while none is. --}}
@php($anyOpen = collect(\App\Support\Site\SitePages::COURSES)->contains(fn (string $slug): bool => \App\Support\Site\SitePages::courseOpen($slug)))

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span><x-site.skin :game="__('pages.landing.game.courses_kick')" :serious="__('pages.site.courses')" /></p>
            @unless ($anyOpen)
                <p class="s-soonpill"><x-site.soon /></p>
            @endunless
            <h1 id="page-h"><x-site.skin :game="__('pages.landing.game.courses_title')" :serious="__('site.courses.index.headline')" /></h1>
            <p class="s-lede">{{ __('site.courses.index.lead') }}</p>
        </section>

        {{-- The free course first, to its own page. --}}
        <section class="s-wrap s-sec" aria-labelledby="group-free">
            <h2 id="group-free" class="s-kick">{{ __('site.courses.index.free_kick') }}</h2>
            <a href="/features/course" class="s-card s-freecourse">
                <span class="s-freecourse-stack" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>
                <span><b>{{ __('site.courses.index.free_name') }}</b><span>{{ __('site.courses.index.free_summary') }}</span></span>
            </a>
        </section>

        @foreach ($groups as $key => $group)
            <section class="s-wrap s-sec" aria-labelledby="group-{{ $key }}">
                <div class="s-headrow">
                    <h2 id="group-{{ $key }}" class="s-kick">{{ $group['name'] }}</h2>
                    <span class="s-countline" style="margin: 0">{{ trans_choice('pages.site.course_count', count($group['courses'])) }}</span>
                </div>
                <ul class="s-cards s-coursecards">
                    @foreach ($group['courses'] as $slug)
                        <li>
                            <a href="/courses/{{ $slug }}" class="s-card">
                                <b>{{ __('site.courses.pages.'.$slug.'.name') }}@if (\App\Support\Site\SitePages::courseOpen($slug)) <span class="s-soon s-open">{{ __('pages.site.course_open') }}</span>@endif</b>
                                <span>{{ __('site.courses.pages.'.$slug.'.summary') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @include('site._course-today')
    </main>
    <x-site.footer />
@endsection
