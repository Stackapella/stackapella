{{-- A course's lesson page (/courses/<course>/<page>; docs/seo-lesson-pages-plan.md): the trail, the lesson's (or unit's)
     title and its line, Sing this lesson into the app (and, beside them on a lesson, its unit's pages as chips), then each lesson's teaching as the course writes it (its learn
     cards), its real voices where a recording is published, what you sing (each step's one line, never how it's
     scored), its "Did you know?" with the source, the
     course's safety line, the pages before and after, About Stackapella and the free tools.
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
{{-- A lesson whose unit has more pages shows them as chips beside its heading (Hillel's part page redesign, 2026-11-10;
     the courses lead: the unit's cards move up, no facts row); a unit page, or a unit of one page, keeps a one-column top. --}}
@php($chips = $page['kind'] === 'lesson' && count($unitPages) > 1)

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-head s-spart s-lesson-top" aria-labelledby="page-h">
            <div @class(['s-spart-hero' => $chips])>
                <div>
                    {{-- The site's one trail (marketing's x-site.crumbs); its BreadcrumbList is already in the page's own data. --}}
                    <x-site.crumbs :trail="[[__('pages.site.courses'), '/courses'], [$page['courseName'], $page['up']], [$page['name']]]" />
                    <p class="s-teyebrow">{{ $page['kind'] === 'unit' ? __('site.lessons.unit', ['number' => $page['unitNumber']]) : __('site.lessons.unitOf', ['number' => $page['unitNumber'], 'unit' => $page['unit']]) }}</p>
                    <h1 id="page-h">{{ $page['name'] }}</h1>
                    <p class="s-lede">{{ $page['description'] ?? __('site.lessons.lead', ['course' => $page['courseName']]) }}</p>
                    <div class="s-actions s-actions-row">
                        <a href="{{ $page['start'] }}" class="btn-primary btn-lg" data-lesson-start>{{ $page['kind'] === 'unit' ? __('site.lessons.startUnit') : __('site.lessons.start') }} <x-site.icon name="arrow" size="18" /></a>
                    </div>
                    <p class="s-soonline">{{ __('site.lessons.free_line') }}</p>
                </div>
                @if ($chips)
                    <div class="s-spart-side">
                        <h2 id="unit-h" class="s-kick s-spart-switch">{{ __('site.lessons.inUnit', ['unit' => $page['unit']]) }}</h2>
                        <ul class="s-spart-chips" aria-labelledby="unit-h">
                            @foreach ($unitPages as $other)
                                <li>
                                    @if ($other['url'] === $page['url'])
                                        <span class="s-spart-chip" aria-current="page"><b>{{ $other['name'] }}</b><span>{{ __('site.lessons.here') }}</span></span>
                                    @else
                                        <a href="{{ $other['url'] }}" class="s-spart-chip"><b>{{ $other['name'] }}</b></a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </section>

        @foreach ($page['lessons'] as $lesson)
            <section class="s-wrap s-sec2 s-tool-guide s-lesson-sec" aria-labelledby="lesson-{{ $loop->iteration }}" data-lesson="{{ $lesson['id'] }}">
                <h2 id="lesson-{{ $loop->iteration }}">{{ $single ? __('site.lessons.teaches') : ($lesson['challenge'] ? __('site.lessons.challenge', ['name' => $lesson['name']]) : $lesson['name']) }}</h2>
                {{-- Hillel's width design (2026-11-11): from 900 px the teaching at left, one reading column as the course writes
                     it, and beside it what you do with it: its real voices and Try it in one panel, What you sing, and its
                     "Did you know?". A phone, and a lesson with none of those, stacks them in this order. --}}
                {{-- A section with no teaching (a challenge) has its steps in the reading column under its heading (the courses lead). --}}
                @php($noTeach = collect($lesson['teach'])->every(fn (array $teach): bool => $teach['headline'] === '' && $teach['cards'] === []))
                <div @class(['s-lesson-cols' => ! empty($clips[$lesson['id']]) || ($tryOn && $lesson['choose']) || ($lesson['sing'] !== [] && ! $noTeach) || $lesson['fact']])>
                    <div class="s-lesson-read">
                        @foreach ($lesson['teach'] as $teach)
                            @if ($teach['headline'] !== '')
                                <p>{{ $teach['headline'] }}</p>
                            @endif
                            @foreach ($teach['cards'] as $card)
                                <p><b>{{ $card['title'] }}.</b> {{ $card['detail'] }}</p>
                            @endforeach
                        @endforeach
                        @if ($noTeach && $lesson['sing'] !== [])
                            @include('site.courses._lesson-sing', ['sing' => $lesson['sing']])
                        @endif
                    </div>
                    <div class="s-lesson-side">
                        {{-- In the order a singer uses them (the courses lead, 2026-11-11): its real voices (where a beta singer's
                             recording is published, never a clip's brief alone) right after the teaching, then What you sing, then
                             Try one round (docs/lesson-page-try-it-plan.md; the lesson_try switch: the lesson's first listen-and-choose
                             round of exactly those steps, no mic, js/public/lesson-try.js on the reader's tap), then its "Did you know?". --}}
                        @if (! empty($clips[$lesson['id']]))
                            <div class="s-spart-panel s-lesson-panel">
                                <div>
                                    <h3 class="font-bold">{{ __('site.lessons.hear') }}</h3>
                                    <div class="s-lesson-clips" data-lesson-clips>
                                        @foreach ($clips[$lesson['id']] as $clip)
                                            <figure class="s-card">
                                                <audio controls preload="none" src="{{ $clip['url'] }}" class="w-full"></audio>
                                                @if ($clip['credit'])
                                                    <figcaption>{{ __('site.lessons.sungBy', ['name' => $clip['credit']]) }}</figcaption>
                                                @endif
                                            </figure>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                        @if (! $noTeach && $lesson['sing'] !== [])
                            @include('site.courses._lesson-sing', ['sing' => $lesson['sing']])
                        @endif
                        @if ($tryOn && $lesson['choose'])
                            <div class="s-spart-panel s-lesson-panel">
                                <div class="s-card" data-lesson-try>
                                    <b>{{ __('site.lessons.try') }}</b>
                                    <script type="application/json" data-lesson-try-round>@json([...$lesson['choose'], 'start' => \App\Support\Http\Hosts::app('/learn/'.$lesson['id'].'?ref=lesson-try')])</script>
                                    <script type="application/json" data-lesson-try-words>@json($tryWords)</script>
                                    <button type="button" class="btn-secondary mt-2 self-start" data-lesson-try-start>{{ __('site.lessons.tryStart') }}</button>
                                </div>
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
                    </div>
                </div>
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
