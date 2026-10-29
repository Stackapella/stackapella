{{-- A course on Plus (/courses/<slug>; docs/plus-courses-plan.md): what it is and what you'll learn.
     Once it's open (SitePages::courseOpen: the plus_courses flag and its status in
     web/course/courses.json) it says so and leads to its free first lesson; until then Coming soon,
     with what it still waits for said plainly (listening the app can't do yet,
     care for loud or high singing), then the free course that works today and the group's other
     courses. Below the hero: the units and lessons from the course's own file, what the app listens
     for and how it scores, two real screens, singing safely and the songs. Promises nothing beyond
     the plan. Nexus only. Words: lang/en/site.php `courses`. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php($group = (array) (\App\Support\Site\SitePages::courseGroups()[$page['group']] ?? []))
@php($open = \App\Support\Site\SitePages::courseOpen($page['slug']))
@php($outline = \App\Support\Site\SitePages::courseOutline($page['slug']))

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div class="s-split">
                <div>
                    <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                    @if ($open)
                        <p class="s-soonpill"><span class="s-soon s-open">{{ __('pages.site.course_open') }}</span></p>
                    @else
                        <p class="s-soonpill"><x-site.soon /></p>
                    @endif
                    <h1 id="page-h">{{ $page['headline'] }}</h1>
                    <p class="s-lede">{{ $page['lead'] }}</p>
                    <p class="s-soonline">{{ $open ? __('pages.site.course_open_line') : __('pages.site.course_soon') }}</p>
                    @if (! $open && is_string($page['needs'] ?? null))
                        <p class="s-soonline">{{ __('pages.site.course_needs', ['what' => $page['needs']]) }}</p>
                    @endif
                    @if ($page['health'] ?? false)
                        <p class="s-soonline">{{ __('pages.site.course_health') }}</p>
                    @endif
                    <div class="s-actions s-actions-row">
                        @if ($open)
                            <a href="{{ \App\Support\Http\Hosts::app('/learn/c/'.$page['slug']) }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                            <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
                        @else
                            <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                        @endif
                    </div>
                </div>
                <div class="s-card3d s-iconhero" aria-hidden="true">
                    <span class="gem"><x-site.icon :name="$group['icon'] ?? 'spark'" size="44" /></span>
                    <span class="s-stackbig">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                </div>
            </div>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="learn-h">
            <h2 id="learn-h" class="s-kick">{{ __('pages.site.course_learn') }}</h2>
            <ul class="s-ticks s-ticks-2">
                @foreach ((array) ($page['learn'] ?? []) as $point)
                    <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                @endforeach
            </ul>
        </section>

        {{-- The course itself, unit by unit, from its own file (SitePages::courseOutline), so the page
             follows the course; the first lesson is free. Stages in the game skin, units in the serious. --}}
        @if ($outline !== null)
            <section class="s-wrap s-sec" aria-labelledby="units-h">
                <div class="s-units-head">
                    <h2 id="units-h" class="s-kick">{{ __('pages.site.course_units') }}</h2>
                    <p class="s-units-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="s-units">
                    @foreach ($outline['units'] as $unit)
                        <li class="s-unit">
                            @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                            @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                            <p class="s-unit-n"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            <ol>
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- What the app listens for and how a lesson is scored (stars in the game skin, the measure in
             the serious), beside two real screens of the course (web/tool/course_shots.py). --}}
        @if (is_string($page['measures'] ?? null))
            <section class="s-wrap s-sec" aria-labelledby="hears-h">
                <div class="s-split s-course-hears">
                    <div>
                        <h2 id="hears-h" class="s-kick">{{ __('pages.site.course_hears') }}</h2>
                        <p class="s-lede">{{ $page['measures'] }}</p>
                        @if (is_array($page['scoring'] ?? null))
                            <p class="s-lede"><x-site.say :text="$page['scoring']" /></p>
                        @endif
                    </div>
                    <div class="s-course-shots">
                        @foreach ((array) ($page['shots'] ?? []) as $alt)
                            @php($shotId = 'course-'.$page['slug'].'--'.$loop->iteration)
                            <x-site.appshot :id="$shotId" :alt="$alt" />
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (($page['safety'] ?? []) !== [] || ($page['songs'] ?? []) !== [])
            <section class="s-wrap s-sec s-course-notes">
                @if (($page['safety'] ?? []) !== [])
                    <div aria-labelledby="safety-h">
                        <h2 id="safety-h" class="s-kick">{{ __('pages.site.course_safety') }}</h2>
                        <ul class="s-ticks">
                            @foreach ((array) $page['safety'] as $note)
                                <li><x-site.icon name="info" size="18" />{{ $note }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (($page['songs'] ?? []) !== [])
                    <div aria-labelledby="songs-h">
                        <h2 id="songs-h" class="s-kick">{{ __('pages.site.course_songs') }}</h2>
                        <ul class="s-course-songs">
                            @foreach ((array) $page['songs'] as [$song, $credit])
                                <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        @endif

        @include('site._course-today')

        <section class="s-wrap s-sec" aria-labelledby="more-h">
            <h2 id="more-h" class="s-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
            <ul class="s-chips">
                @foreach ((array) ($group['courses'] ?? []) as $slug)
                    @continue($slug === $page['slug'])
                    <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
                @endforeach
            </ul>
        </section>
    </main>
    <x-site.footer />
@endsection
