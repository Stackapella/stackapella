{{-- The home's paths with pages of their own (Hillel, 2026-11-23): /for/learners and /for/singers, in Hillel's 2026-11-24
     design: the promise in two tones beside a real screen of the app, the steps as a numbered strip, then each step a
     section (its words and a small picture on the left, what the site already has for it on the right: songs, courses,
     tools, guides, each its own card), the questions, and the way in again under the four bars. Nothing about AI
     (that's the AI landing's). Words: site.for.pages.<who> (marketing's) and the cards' own; what's listed: PathPages.
     Game mode keeps its arcade dress over the same markup. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php($learners = $page['slug'] === 'learners')
@php($heads = (array) $page['heads'])
@php($lines = (array) $page['lines'])
@php($labels = (array) $page['labels'])
@php($steps = array_keys($lines))
@php([$plain, $accent] = \App\Support\Site\PathPages::split((string) $page['headline']))

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="s-aud">
        <section class="s-wrap s-aud-head" aria-labelledby="page-h">
            <div>
                <x-site.crumbs :trail="[[__('pages.site.who_crumb'), '/for'], [$page['name']]]" :ld="true" />
                {{-- The page names its people (Hillel, 2026-11-24), over the promise in two tones as the design draws it. --}}
                <h1 id="page-h" class="s-aud-kick">{{ $page['h1'] }}</h1>
                <p class="s-aud-h1">{{ $plain }} <em>{{ $accent }}</em></p>
                <p class="s-lede">{{ $page['lead'] }}</p>
                <div class="s-actions s-actions-row">
                    <a href="{{ $first }}" class="btn-primary btn-lg"@if ($learners) data-funnel-start @endif>{{ $page['start'] }} <x-site.icon name="arrow" size="18" /></a>
                </div>
                @if ($learners)
                    <p class="s-dir-note">{{ __('landing.hero.try_note') }}</p>
                @endif
            </div>
            {{-- A real screen of the app that shows the promise. --}}
            @php($shot = \App\Support\Site\PathPages::shot($page['slug']))
            <div class="s-aud-shot"><x-site.appshot :id="$shot['id']" :alt="$shot['alt']" /></div>
        </section>

        {{-- The steps at a glance, each to its section. --}}
        <nav class="s-aud-strip" aria-label="{{ $page['h1'] }}">
            <ol class="s-wrap">
                @foreach ($steps as $key)
                    <li class="s-v{{ $loop->iteration }}"><a href="#{{ $key }}-h"><b aria-hidden="true">{{ $loop->iteration }}</b><span>{{ $heads[$key] }}</span></a></li>
                @endforeach
            </ol>
        </nav>

        @foreach ($steps as $key)
            @php([$hPlain, $hAccent] = \App\Support\Site\PathPages::split((string) $heads[$key]))
            <section @class(['s-wrap', 's-aud-step', 's-v'.$loop->iteration, 's-aud-wide' => in_array($key, ['better', 'share'], true)]) aria-labelledby="{{ $key }}-h">
                <div class="s-aud-say">
                    <p class="s-aud-n" aria-hidden="true">{{ $loop->iteration }}</p>
                    <h2 id="{{ $key }}-h" class="s-aud-h2">{{ $hPlain }} <em>{{ $hAccent }}</em></h2>
                    @if ($lines[$key] !== '')
                        <p class="s-lede">{{ $lines[$key] }}</p>
                    @endif
                    {{-- The step's small picture, in the parts' colours (no words of its own beyond the voices' names). --}}
                    @if (in_array($key, ['hear', 'pick'], true))
                        <div class="s-aud-pic" aria-hidden="true">
                            <span class="s-aud-part s-v1 s-aud-on"><i class="s-aud-orb">{{ mb_substr((string) __('pages.landing.hero.parts.soprano'), 0, 1) }}</i>{{ __('pages.landing.hero.parts.soprano') }}<small>{{ __('landing.solo_on') }}</small></span>
                            <span class="s-aud-row">
                                @foreach (['alto', 'tenor', 'bass'] as $part)
                                    <span class="s-aud-part s-aud-wait">{{ __('pages.landing.hero.parts.'.$part) }}</span>
                                @endforeach
                            </span>
                        </div>
                    @elseif (in_array($key, ['sing', 'record'], true))
                        <div class="s-aud-pic" aria-hidden="true">
                            <span class="s-aud-row">
                                @foreach (['soprano', 'alto'] as $part)
                                    <span class="s-aud-part s-v{{ $loop->iteration }}"><x-site.icon name="check" size="14" />{{ __('pages.landing.hero.parts.'.$part) }}</span>
                                @endforeach
                            </span>
                            <span class="s-aud-part s-v3 s-aud-on"><i class="s-aud-rec"></i>{{ __('pages.landing.hero.parts.tenor') }}</span>
                        </div>
                    @endif
                </div>

                @unless (in_array($key, ['better', 'share'], true))
                <div class="s-aud-what">
                    {{-- Songs: learners' easiest first, singers' harder first, each to its page. --}}
                    @if (in_array($key, ['hear', 'pick'], true) && $content['songs'] !== [])
                        <div class="s-headrow"><h3 class="s-kick">{{ $labels['songs'] ?? __('pages.songs.all_songs') }}</h3><a href="/songs" class="s-arrow">{{ __('pages.songs.all_songs') }} <x-site.icon name="arrow" size="16" /></a></div>
                        <ul class="s-aud-songs">
                            @foreach ($content['songs'] as $song)
                                <li><a href="{{ $song['url'] }}"><span class="s-aud-disc" aria-hidden="true"><x-site.icon name="play" size="14" /></span><b>{{ $song['name'] }}</b><small class="s-aud-level s-aud-{{ $song['level'] ?? 'beginner' }}">{{ $song['summary'] }}</small></a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($key === 'hear' && $content['guidesHear'] !== [])
                        @include('site._aud-guides', ['more' => true, 'guides' => $content['guidesHear']])
                    @endif

                    {{-- The way to sing it: the course or the studio first, dark, then the courses that fit. --}}
                    @if (in_array($key, ['sing', 'record'], true))
                        <div class="s-headrow"><h3 class="s-kick">{{ $labels['courses'] }}</h3><a href="/courses" class="s-arrow">{{ __('pages.site.all_courses') }} <x-site.icon name="arrow" size="16" /></a></div>
                        <ul class="s-aud-courses">
                            @foreach ($content['features'] as $card)
                                <li class="s-aud-first"><a href="{{ $card['url'] }}"><small>{{ __('pages.landing.start_here') }}</small><b>{{ $card['name'] }}</b><span>{{ $card['summary'] }}</span><i aria-hidden="true"><x-site.icon name="arrow" size="18" /></i></a></li>
                            @endforeach
                            @foreach ($content['courses'] as $slug)
                                <li><a href="/courses/{{ $slug }}"><b>{{ __('site.courses.pages.'.$slug.'.name') }}@if (\App\Support\Site\SitePages::courseOpen($slug)) <span class="s-soon s-open">{{ __('pages.site.course_open') }}</span>@endif</b><span>{{ __('site.courses.pages.'.$slug.'.summary') }}</span></a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if (in_array($key, ['sing', 'mix'], true) && $content['tools'] !== [])
                        <div class="s-headrow"><h3 class="s-kick">{{ $labels['tools'] ?? __('site.tools.index.eyebrow') }}</h3><a href="{{ $content['toolsMore'][0] }}" class="s-arrow">{{ $content['toolsMore'][1] }} <x-site.icon name="arrow" size="16" /></a></div>
                        <ul class="s-aud-tools">
                            @foreach ($content['tools'] as $tool)
                                <li><a href="{{ $tool['url'] }}"><b>{{ $tool['name'] }}</b><span>{{ $tool['summary'] }}</span></a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($key === 'sing' && $content['guidesSing'] !== [])
                        @include('site._aud-guides', ['more' => false, 'guides' => $content['guidesSing']])
                    @endif
                    @if ($key === 'mix' && $content['guidesMix'] !== [])
                        @include('site._aud-guides', ['more' => false, 'guides' => $content['guidesMix']])
                    @endif
                </div>
                @endunless

                {{-- How progress works, in the course page's own words, each skin its own: three cards across. --}}
                @if ($key === 'better')
                    <ul class="s-aud-cards">
                        @foreach ($content['progress'] as $section)
                            <li>
                                <span class="s-aud-top s-v{{ [3, 1, 4][$loop->index % 3] }}" aria-hidden="true">@foreach ([[1, 34], [2, 56], [3, 78], [4, 100]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                                <b>@if (is_array($section['heading']))<x-site.skin :game="$section['heading']['game']" :serious="$section['heading']['serious']" />@else{{ $section['heading'] }}@endif</b>
                                <span>@if (is_array($section['body']))<x-site.skin :game="$section['body']['game']" :serious="$section['body']['serious']" />@else{{ $section['body'] }}@endif</span>
                                @if ($loop->first)
                                    <a href="/features/course" class="s-arrow">{{ __('site.features.pages.course.name') }} <x-site.icon name="arrow" size="16" /></a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Sharing the whole song: the share card, friends on the other parts, and Plus (never its AI lines). --}}
                @if ($key === 'share')
                    <ul class="s-aud-cards">
                        @foreach ([...$content['share'], ...$content['friends']] as $card)
                            <li>
                                <span class="s-aud-top s-v4" aria-hidden="true">@foreach ([[1, 34], [2, 56], [3, 78], [4, 100]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                                <a href="{{ $card['url'] }}" class="s-aud-cardlink"><b>{{ $card['name'] }}</b></a>
                                <span>{{ $card['summary'] }}</span>
                            </li>
                        @endforeach
                        @if ($content['plus'] !== [])
                            <li class="s-aud-plus">
                                <div class="s-headrow"><b>{{ __('pages.landing.plans_plus') }}</b><a href="/pricing" class="s-arrow">{{ __('pages.site.see_plus') }} <x-site.icon name="arrow" size="16" /></a></div>
                                <ul>
                                    @foreach ($content['plus'] as [$gain, $fact])
                                        <li><x-site.icon name="check" size="16" /><span><b>{{ $gain }}</b> {{ $fact }}</span></li>
                                    @endforeach
                                </ul>
                            </li>
                        @endif
                    </ul>
                @endif
            </section>
        @endforeach

        {{-- The narrower page for the same people, once: complete beginners, or a cappella singers. --}}
        <div class="s-wrap">
            <a href="{{ $content['also'] }}" class="s-aud-also"><span>{{ $page['also'] }}</span><x-site.icon name="arrow" size="18" /></a>
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

        @php([$ePlain, $eAccent] = \App\Support\Site\PathPages::split((string) $page['end']['title']))
        <section class="s-wrap s-aud-end" aria-labelledby="end-h">
            {{-- The four bars as the logo has them: one left edge (design rules). --}}
            <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
            <h2 id="end-h">{{ $ePlain }} <em>{{ $eAccent }}</em></h2>
            <p class="s-final-btns"><a href="{{ $first }}" class="btn-primary btn-lg"@if ($learners) data-funnel-start @endif>{{ $page['start'] }} <x-site.icon name="arrow" size="18" /></a></p>
            <p>{{ $page['end']['body'] }}</p>
        </section>
    </main>
    <x-site.footer />
@endsection
