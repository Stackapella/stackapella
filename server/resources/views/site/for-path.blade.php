{{-- The home's paths with pages of their own (Hillel, 2026-11-23: "the home's Leaders path has its own page; do the same
     for Singers and Learners"): /for/learners and /for/singers, in For choir directors' calm density. The page names its
     people, says its promise, and gives one way in; then short sections of what the site already has for that way in,
     each drawn as its own card (songs, courses, tools, guides), the questions, and the way in again. Nothing about AI
     (that's /ai-harmony-singing's). Words: site.for.pages.<who> (marketing's), the cards' own; what's listed: PathPages. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php($learners = $page['slug'] === 'learners')
@php($heads = (array) $page['heads'])
@php($lines = (array) $page['lines'])
@php($labels = (array) $page['labels'])

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="s-dir">
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div class="s-split">
                <div>
                    <x-site.crumbs :trail="[[__('pages.site.who_crumb'), '/features#who-h'], [$page['name']]]" :ld="true" />
                    <h1 id="page-h">{{ $page['h1'] }}</h1>
                    <p class="s-lede"><b>{{ $page['headline'] }}</b> {{ $page['lead'] }}</p>
                    <div class="s-actions s-actions-row">
                        <a href="{{ $first }}" class="btn-primary btn-lg"@if ($learners) data-funnel-start @endif>{{ $page['start'] }} <x-site.icon name="arrow" size="18" /></a>
                    </div>
                    @if ($learners)
                        <p class="s-dir-note">{{ __('landing.hero.try_note') }}</p>
                    @endif
                </div>
                {{-- A real screen of the app that shows the promise, framed by itself (no card around it). --}}
                @php($shot = \App\Support\Site\PathPages::shot($page['slug']))
                <div class="s-heroshot"><x-site.appshot :id="$shot['id']" :alt="$shot['alt']" /></div>
            </div>
        </section>

        @foreach (array_keys($lines) as $key)
            <section class="s-wrap s-sec" aria-labelledby="{{ $key }}-h">
                <h2 id="{{ $key }}-h" class="s-title">{{ $heads[$key] }}</h2>
                @if ($lines[$key] !== '')
                    <p class="s-lede">{{ $lines[$key] }}</p>
                @endif

                {{-- Songs: learners' easiest first, singers' harder first, each to its page. --}}
                @if (in_array($key, ['hear', 'pick'], true) && $content['songs'] !== [])
                    @include('site._path-cards', ['label' => $labels['songs'] ?? null, 'more' => ['/songs', __('pages.songs.all_songs')], 'cards' => $content['songs']])
                @endif
                @if ($key === 'hear' && $content['guidesHear'] !== [])
                    @include('site._path-cards', ['label' => __('pages.guides.eyebrow'), 'more' => ['/guides', __('pages.guides.all')], 'cards' => $content['guidesHear']])
                @endif

                {{-- The way to sing it: the course or the studio, then the courses, tools and guides that fit. --}}
                @if (in_array($key, ['sing', 'record'], true))
                    <div class="s-headrow">
                        <h3 class="s-kick">{{ $labels['courses'] }}</h3>
                        <a href="/courses" class="s-arrow">{{ __('pages.site.all_courses') }} <x-site.icon name="arrow" size="16" /></a>
                    </div>
                    <ul class="s-cards s-coursecards">
                        {{-- The course or the studio first: the way to sing it, then the courses that fit. --}}
                        @foreach ($content['features'] as $card)
                            <li class="s-neon3"><a href="{{ $card['url'] }}" class="s-card"><b>{{ $card['name'] }}</b><span>{{ $card['summary'] }}</span></a></li>
                        @endforeach
                        @foreach ($content['courses'] as $slug)
                            <li class="s-neon{{ $loop->index % 3 + 1 }}">
                                <a href="/courses/{{ $slug }}" class="s-card">
                                    <b>{{ __('site.courses.pages.'.$slug.'.name') }}@if (\App\Support\Site\SitePages::courseOpen($slug)) <span class="s-soon s-open">{{ __('pages.site.course_open') }}</span>@endif</b>
                                    <span>{{ __('site.courses.pages.'.$slug.'.summary') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (in_array($key, ['sing', 'mix'], true) && $content['tools'] !== [])
                    @include('site._path-cards', ['label' => $labels['tools'] ?? __('site.tools.index.eyebrow'), 'more' => $content['toolsMore'], 'cards' => $content['tools']])
                @endif
                @if ($key === 'sing' && $content['guidesSing'] !== [])
                    @include('site._path-cards', ['label' => __('pages.guides.eyebrow'), 'more' => null, 'cards' => $content['guidesSing']])
                @endif
                @if ($key === 'mix' && $content['guidesMix'] !== [])
                    @include('site._path-cards', ['label' => __('pages.guides.eyebrow'), 'more' => null, 'cards' => $content['guidesMix']])
                @endif

                {{-- How progress works, in the course page's own words, each skin its own. --}}
                @if ($key === 'better')
                    <ul class="s-cards">
                        @foreach ($content['progress'] as $section)
                            <li>
                                <div class="s-card">
                                    <b>@if (is_array($section['heading']))<x-site.skin :game="$section['heading']['game']" :serious="$section['heading']['serious']" />@else{{ $section['heading'] }}@endif</b>
                                    <span>@if (is_array($section['body']))<x-site.skin :game="$section['body']['game']" :serious="$section['body']['serious']" />@else{{ $section['body'] }}@endif</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="s-carols-link"><a href="/features/course" class="s-lk"><x-site.arrow-end :text="__('site.features.pages.course.name')" spaced /></a></p>
                @endif

                {{-- Sharing the whole song: the share card, friends on the other parts, and Plus (never its AI lines). --}}
                @if ($key === 'share')
                    @include('site._path-cards', ['label' => null, 'more' => null, 'cards' => $content['share']])
                    @include('site._path-cards', ['label' => $labels['friends'], 'more' => null, 'cards' => $content['friends']])
                    @if ($content['plus'] !== [])
                        <div class="s-headrow">
                            <h3 class="s-kick">{{ __('pages.landing.plans_plus') }}</h3>
                            <a href="/pricing" class="s-arrow">{{ __('pages.site.see_plus') }} <x-site.icon name="arrow" size="16" /></a>
                        </div>
                        <ul class="s-cards">
                            @foreach ($content['plus'] as [$gain, $fact])
                                <li><div class="s-card"><b>{{ $gain }}</b><span>{{ $fact }}</span></div></li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </section>
        @endforeach

        {{-- The narrower page for the same people, once: complete beginners, or a cappella singers. --}}
        <div class="s-wrap s-sec">
            <p class="s-carols-link"><a href="{{ $content['also'] }}" class="s-lk"><x-site.arrow-end :text="$page['also']" spaced /></a></p>
        </div>

        @if ($content['faq'] !== [])
            <section class="s-wrap s-sec" aria-labelledby="faq-h">
                <div class="s-two">
                    <h2 id="faq-h" class="s-title">{{ $heads['faq'] }}</h2>
                    <div class="s-faq s-faq-round">
                        @foreach ($content['faq'] as [$question, $answer])
                            <details>
                                <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                                <p>{{ $answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
            <x-site.json-ld :data="[['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $content['faq'])]]" />
        @endif

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ $page['end']['title'] }}</h2>
                <p>{{ $page['end']['body'] }}</p>
                <p class="s-final-btns"><a href="{{ $first }}" class="btn-primary btn-lg"@if ($learners) data-funnel-start @endif>{{ $page['start'] }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
