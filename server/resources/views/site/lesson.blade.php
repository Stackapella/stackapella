{{-- A course's lesson page (/courses/<course>/<page>; docs/seo-lesson-pages-plan.md): the trail, the lesson's (or unit's)
     title and its line, Sing this lesson into the app, then each lesson's teaching as the course writes it (its learn
     cards), its real voices where a recording is published, what you sing (each step's one line, never how it's
     scored), its "Did you know?" with the source, the
     course's safety line, the unit's other pages, the pages before and after, About Stackapella and the free tools.
     The content's words are text, never HTML. Words: lang/en/site.php `lessons`; titles and descriptions
     lang/en/lessons.php (marketing's). --}}
@extends('public.layout')

{{-- The arcade screen in the game skin (<html data-gs>), as the reference pages have it. --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
    <x-site.json-ld :data="$ld" />
@endsection

@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))
@php($single = count($page['lessons']) === 1)

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-thead s-thead-hub" aria-labelledby="page-h">
            {{-- The site's one trail (marketing's x-site.crumbs); its BreadcrumbList is already in the page's own data. --}}
            <x-site.crumbs :trail="[[__('pages.site.courses'), '/courses'], [$page['courseName'], $page['up']], [$page['name']]]" />
            <p class="s-teyebrow">{{ $page['kind'] === 'unit' ? __('site.lessons.unit', ['number' => $page['unitNumber']]) : __('site.lessons.unitOf', ['number' => $page['unitNumber'], 'unit' => $page['unit']]) }}</p>
            <h1 id="page-h">{{ $page['name'] }}</h1>
            <p class="s-lede">{{ $page['description'] ?? __('site.lessons.lead', ['course' => $page['courseName']]) }}</p>
            <div class="s-actions s-actions-row">
                <a href="{{ $page['start'] }}" class="btn-primary btn-lg" data-lesson-start>{{ $page['kind'] === 'unit' ? __('site.lessons.startUnit') : __('site.lessons.start') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
            <p class="s-soonline">{{ __('site.lessons.free_line') }}</p>
        </section>

        @foreach ($page['lessons'] as $lesson)
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="lesson-{{ $loop->iteration }}" data-lesson="{{ $lesson['id'] }}">
                <h2 id="lesson-{{ $loop->iteration }}">{{ $single ? __('site.lessons.teaches') : ($lesson['challenge'] ? __('site.lessons.challenge', ['name' => $lesson['name']]) : $lesson['name']) }}</h2>
                @foreach ($lesson['teach'] as $teach)
                    @if ($teach['headline'] !== '')
                        <p>{{ $teach['headline'] }}</p>
                    @endif
                    @foreach ($teach['cards'] as $card)
                        <p><b>{{ $card['title'] }}.</b> {{ $card['detail'] }}</p>
                    @endforeach
                @endforeach
                {{-- Its real voices, where a beta singer's recording is published (never a clip's brief alone). --}}
                @if (! empty($clips[$lesson['id']]))
                    <h3 class="mt-6 font-bold">{{ __('site.lessons.hear') }}</h3>
                    <div class="mt-3 grid max-w-[64ch] gap-3" data-lesson-clips>
                        @foreach ($clips[$lesson['id']] as $clip)
                            <figure class="s-card">
                                <audio controls preload="none" src="{{ $clip['url'] }}" class="w-full"></audio>
                                @if ($clip['credit'])
                                    <figcaption>{{ __('site.lessons.sungBy', ['name' => $clip['credit']]) }}</figcaption>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                @endif
                @if ($lesson['sing'] !== [])
                    <h3 class="mt-6 font-bold">{{ __('site.lessons.sing') }}</h3>
                    <ol class="s-tool-how">
                        @foreach ($lesson['sing'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ol>
                @endif
                {{-- Try one round on the page (docs/lesson-page-try-it-plan.md; the lesson_try switch): the lesson's first
                     listen-and-choose round, no mic; its data in the page, js/public/lesson-try.js on the reader's tap. --}}
                @if ($tryOn && $lesson['choose'])
                    <div class="s-card mt-6 max-w-[64ch]" data-lesson-try>
                        <b>{{ __('site.lessons.try') }}</b>
                        <script type="application/json" data-lesson-try-round>@json([...$lesson['choose'], 'start' => \App\Support\Http\Hosts::app('/learn/'.$lesson['id'].'?ref=lesson-try')])</script>
                        <script type="application/json" data-lesson-try-words>@json($tryWords)</script>
                        <button type="button" class="btn-secondary mt-2 self-start" data-lesson-try-start>{{ __('site.lessons.tryStart') }}</button>
                    </div>
                @endif
                @if ($lesson['fact'])
                    <aside class="s-card mt-6 max-w-[64ch]" aria-label="{{ __('site.lessons.fact') }}" data-lesson-fact>
                        <b>{{ __('site.lessons.fact') }}</b>
                        <span>{{ $lesson['fact']['text'] }}</span>
                        @if ($lesson['fact']['source'])
                            <span>{{ __('site.lessons.source') }}
                                @if ($lesson['fact']['url'])<a href="{{ $lesson['fact']['url'] }}" class="s-lk" rel="nofollow noopener" target="_blank">{{ $lesson['fact']['source'] }}</a>@else{{ $lesson['fact']['source'] }}@endif
                            </span>
                        @endif
                    </aside>
                @endif
            </section>
        @endforeach

        {{-- Singing safely, with the exercise (the courses lead): the course's own line, and belting's daily cap. --}}
        <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="safe-h" data-lesson-safety>
            <h2 id="safe-h">{{ __('site.lessons.safe') }}</h2>
            <p>{{ $page['safety'] ?? __('site.lessons.safe_line') }}</p>
            @if ($page['belting'])
                <p>{{ __('site.lessons.belting_cap') }}</p>
            @endif
        </section>

        @if ($page['kind'] === 'lesson' && count($unitPages) > 1)
            <section class="s-wrap s-sec2" aria-labelledby="unit-h">
                <h2 id="unit-h" class="s-kick">{{ __('site.lessons.inUnit', ['unit' => $page['unit']]) }}</h2>
                <ul class="s-cards s-tool-cards">
                    @foreach ($unitPages as $other)
                        <li>
                            <a href="{{ $other['url'] }}" class="s-card" @if ($other['url'] === $page['url']) aria-current="page" @endif>
                                <b>{{ $other['name'] }}</b>
                                <span>{{ $other['url'] === $page['url'] ? __('site.lessons.here') : __('site.lessons.lessonOf', ['course' => $page['courseName']]) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <nav class="s-wrap s-pager md:grid-cols-2" aria-label="{{ __('site.lessons.pager') }}">
            @if ($page['previous'])
                <a href="{{ $page['previous']['url'] }}" class="s-card" rel="prev"><span>{{ __('site.lessons.previous') }}</span><b>{{ $page['previous']['name'] }}</b></a>
            @else
                <a href="{{ $page['up'] }}" class="s-card"><span>{{ __('site.lessons.course') }}</span><b>{{ $page['courseName'] }}</b></a>
            @endif
            @if ($page['next'])
                <a href="{{ $page['next']['url'] }}" class="s-card s-pager-next" rel="next"><span>{{ __('site.lessons.next') }}</span><b>{{ $page['next']['name'] }}</b></a>
            @else
                <a href="{{ $page['up'] }}" class="s-card s-pager-next"><span>{{ __('site.lessons.course') }}</span><b>{{ $page['courseName'] }}</b></a>
            @endif
        </nav>

        @include('site.tools._about', ['tie' => $ix['tie'], 'tryLink' => $ix['try_link']])

        @if (\App\Support\Site\SitePages::hubLive())
            @include('site.tools._directory')
        @endif
    </main>
    <x-site.footer />
    @if ($tryOn)
        <script type="module" src="/js/public/lesson-try.js"></script>
    @endif
@endsection
