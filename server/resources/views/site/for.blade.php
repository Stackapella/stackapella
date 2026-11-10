{{-- A "for" page (/for/<who>), in the redesign's sections (docs/marketing-redesign-plan.md): the
     promise beside the one drawing that matters to them on a raised card; what it does for them as
     compact details (never numbered unless it's an order); the section with a drawing as the
     spotlight on the ink band; any limit as a quiet notice; what they'll use as tiles; the other
     audiences as chips; and the page's own last word on the band. Coaches, while coaching is coming,
     lead to writing to us (Hillel: a plain email, no list). Words: lang/en/site.php `for`. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

{{-- A page's own button in place of Start: the coaches' (while coaching is coming, write to us; once
     coach sign-up opens, the coach_signup flag with the directory still hidden, apply in the app) and
     the organizations' (write to Hillel). --}}
@php($signup = $page['slug'] === 'coaches' && app(\App\Support\Flags::class)->on('coach_signup'))
@php($try = is_array($page['try'] ?? null) && ($page['slug'] !== 'coaches' || \App\Support\Site\SitePages::soon('coaching')) ? $page['try'] : null)
@php($try = $signup ? [__('pages.site.coach_apply'), \App\Support\Http\Hosts::app('/coach/apply')] : $try)

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div @class(['s-split', 's-solo' => empty($page['pic'])])>
                <div>
                    <x-site.crumbs :trail="[[__('pages.site.who_crumb'), '/features#who-h'], [$page['name']]]" :ld="true" />
                    @if ($signup)
                        <p class="s-soonpill"><span class="s-soon s-open">{{ __('pages.site.coach_signup_pill') }}</span></p>
                    @elseif ($page['slug'] === 'coaches' && \App\Support\Site\SitePages::soon('coaching'))
                        <p class="s-soonpill"><x-site.soon /></p>
                    @endif
                    <h1 id="page-h">{{ $page['headline'] }}</h1>
                    <p class="s-lede">{{ $page['lead'] }}</p>
                    @if (in_array($page['slug'], ['choir-singers', 'choir-directors'], true))
                        @include('site._carols-link', ['line' => __('site.carols.links.'.$page['slug'])])
                    @endif
                    @if (is_array($page['song'] ?? null))
                        @include('site._song-link', ['slug' => $page['song']['slug'], 'line' => $page['song']['line']])
                    @endif
                    @if ($signup)
                        <p class="s-soonline">{{ __('pages.site.coach_signup_line') }}</p>
                    @endif
                    <div class="s-actions s-actions-row">
                        @if ($try !== null && ($page['group_first'] ?? false) && is_string($page['group_start'] ?? null))
                            {{-- The group leads (directors: the learning tracks are asked for from the group's page), the page's own `try` beside it. --}}
                            <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref='.$page['group_start']) }}" class="btn-primary btn-lg">{{ __('pages.site.group_start') }} <x-site.icon name="arrow" size="18" /></a>
                            <a href="{{ $try[1] }}" class="btn-secondary btn-lg">{{ $try[0] }}</a>
                            @if (is_string($page['try_note'] ?? null))
                                <p>{{ $page['try_note'] }}</p>
                            @endif
                        @elseif ($try !== null)
                            <a href="{{ $try[1] }}" class="btn-primary btn-lg">{{ $try[0] }} <x-site.icon name="arrow" size="18" /></a>
                            @if (is_string($page['try_note'] ?? null))
                                <p>{{ $page['try_note'] }}</p>
                            @endif
                            {{-- A director can start their choir's group from here (its `group_start` is the ref it's counted by). --}}
                            @if (is_string($page['group_start'] ?? null))
                                <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref='.$page['group_start']) }}" class="btn-secondary btn-lg">{{ __('pages.site.group_start') }}</a>
                            @endif
                        @else
                            <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
                            @if ($page['end']['plus'] ?? false)
                                <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.see_plus') }}</a>
                            @endif
                            <p>{{ __('pages.site.start_note') }}</p>
                        @endif
                    </div>
                </div>
                @if (! empty($page['shot']) && \App\Support\Site\HelpGuide::shot($page['shot']) !== null)
                    <div class="s-card3d s-heroshot"><x-site.appshot :id="$page['shot']" :alt="$page['shot_alt'] ?? ''" /></div>
                @elseif (str_starts_with((string) ($page['pic'] ?? ''), 'icon:'))
                    <div class="s-card3d s-iconhero" aria-hidden="true">
                        <span class="gem"><x-site.icon :name="substr($page['pic'], 5)" size="44" /></span>
                        <span class="s-stackbig">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                    </div>
                @elseif (! empty($page['pic']))
                    <div class="s-card3d s-heropic"><x-site.pic :name="$page['pic']" :hero="$draw['hero']" :pics="$draw['pics']" /></div>
                @endif
            </div>
        </section>

        @include('site._demo-section')

        @if ($page['notes'] !== [])
            <section class="s-wrap s-sec" aria-label="{{ $page['headline'] }}">
                <div class="s-details">
                    @foreach ($page['notes'] as $section)
                        <article class="s-v{{ $loop->index % 4 + 1 }}">
                            <h2><i class="gem" aria-hidden="true"></i>{{ $section['heading'] }}</h2>
                            <p>{{ $section['body'] }}</p>
                            @if (! empty($section['points']))
                                <ul class="s-ticks">
                                    @foreach ($section['points'] as $point)
                                        <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if (is_string($section['soon'] ?? null))
                                <p class="s-soonline"><x-site.soon /> {{ $section['soon'] }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Directors and coaches: the tool under the game (the landing's section, reusable). --}}
        @if (in_array($page['slug'], ['choir-directors', 'coaches'], true))
            @include('site._teams')
        @endif

        {{-- The section with a drawing: the page's spotlight, on the ink band. --}}
        @foreach ($page['rows'] as $section)
            <section class="s-ink" aria-labelledby="row-{{ $loop->iteration }}">
                <div class="s-wrap s-split">
                    <div>
                        <h2 id="row-{{ $loop->iteration }}" class="s-rowtitle">{{ $section['heading'] }}</h2>
                        <p class="s-lede">{{ $section['body'] }}</p>
                        @if (is_string($section['soon'] ?? null))
                            <p class="s-soonline"><x-site.soon /> {{ $section['soon'] }}</p>
                        @endif
                    </div>
                    <div class="s-card3d"><x-site.pic :name="$section['pic']" :hero="$draw['hero']" :pics="$draw['pics']" /></div>
                </div>
            </section>
        @endforeach

        @foreach ($page['notices'] as $section)
            <section class="s-wrap s-sec2" aria-label="{{ $section['heading'] }}">
                <p class="s-quiet"><x-site.icon name="info" size="18" /><span><b>{{ $section['heading'] }}.</b> {{ $section['body'] }}</span></p>
            </section>
        @endforeach

        {{-- The page's questions, if it has them (choir directors'), and the same answers as structured data. --}}
        @if ($page['faq'] !== [])
            <section class="s-wrap s-sec" aria-labelledby="faq-h">
                <div class="s-two">
                    <h2 id="faq-h" class="s-title">{{ __('pages.pricing.faq_title') }}</h2>
                    <div class="s-faq s-faq-round">
                        @foreach ($page['faq'] as [$question, $answer])
                            <details @if ($loop->first) open @endif>
                                <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                                <p>{{ $answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
            <x-site.json-ld :data="[['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $page['faq'])]]" />
        @endif

        {{-- Directors and teachers who run a whole organization: the partner offer (/for/organizations). --}}
        @if (in_array($page['slug'], ['choir-directors', 'music-teachers'], true))
            <section class="s-wrap s-sec2" aria-label="{{ __('pages.site.orgs_heading') }}">
                <p class="s-quiet"><x-site.icon name="users" size="18" /><span><b>{{ __('pages.site.orgs_heading') }}</b> {{ __('pages.site.orgs_body') }} <a href="/for/organizations" class="s-lk">{{ __('pages.site.orgs_link') }}</a></span></p>
            </section>
        @endif

        @if ($cards !== [])
            <section class="s-wrap s-sec" aria-labelledby="cards-h">
                <div class="s-headrow">
                    <h2 id="cards-h" class="s-kick">{{ __('pages.site.uses') }}</h2>
                    <a href="/features" class="s-arrow">{{ __('pages.site.all_features') }} <x-site.icon name="arrow" size="16" /></a>
                </div>
                @include('site._tiles', ['tiles' => array_slice($cards, 0, 3)])
            </section>
        @endif

        {{-- The courses that fit this audience, drawn as the courses page draws them. --}}
        @php($fit = collect($page['courses'] ?? [])->intersect(\App\Support\Site\SitePages::courses())->values()->all())
        @if ($fit !== [])
            <section class="s-wrap s-sec" aria-labelledby="fit-h">
                <div class="s-headrow">
                    <h2 id="fit-h" class="s-kick">{{ __('pages.site.fit_courses') }}</h2>
                    <a href="/courses" class="s-arrow">{{ __('pages.site.all_courses') }} <x-site.icon name="arrow" size="16" /></a>
                </div>
                <ul class="s-cards s-coursecards">
                    @foreach ($fit as $slug)
                        <li>
                            <a href="/courses/{{ $slug }}" class="s-card">
                                <b>{{ __('site.courses.pages.'.$slug.'.name') }}@if (\App\Support\Site\SitePages::courseOpen($slug)) <span class="s-soon s-open">{{ __('pages.site.course_open') }}</span>@endif</b>
                                <span>{{ __('site.courses.pages.'.$slug.'.summary') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- The guides that answer this audience's questions, by their titles. --}}
        @if (! empty($page['guides']))
            <section class="s-wrap s-sec" aria-labelledby="guides-h">
                <div class="s-headrow">
                    <h2 id="guides-h" class="s-kick">{{ __('pages.guides.eyebrow') }}</h2>
                    <a href="/guides" class="s-arrow">{{ __('pages.guides.all') }} <x-site.icon name="arrow" size="16" /></a>
                </div>
                <ul class="s-cards">
                    @foreach ((array) $page['guides'] as $guideSlug)
                        @php($guideDoc = app(\App\Support\Legal\LegalText::class)->read($guideSlug, folder: 'guides'))
                        <li><a href="/guides/{{ $guideSlug }}" class="s-card"><b>{{ $guideDoc['title'] }}</b><span>{{ $guideDoc['description'] }}</span></a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- The other audiences, as chips. --}}
        <section class="s-wrap s-sec" aria-labelledby="also-h">
            <h2 id="also-h" class="s-kick">{{ __('pages.site.also_for') }}</h2>
            <ul class="s-chips">
                @foreach (\App\Support\Site\SitePages::who() as $other)
                    @continue($other['url'] === '/for/'.$page['slug'])
                    <li><a href="{{ $other['url'] }}"><span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon :name="$other['icon']" size="16" /></span>{{ $other['name'] }}</a></li>
                @endforeach
            </ul>
        </section>

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ $page['end']['title'] ?? __('pages.site.final_title') }}</h2>
                <p>{{ $page['end']['body'] ?? __('pages.site.final_body') }}</p>
                <p class="s-final-btns">
                    @if ($try !== null && ($page['group_first'] ?? false) && is_string($page['group_start'] ?? null))
                        <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref='.$page['group_start']) }}" class="btn-primary btn-lg">{{ __('pages.site.group_start') }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="{{ $try[1] }}" class="btn-secondary btn-lg">{{ $try[0] }}</a>
                    @elseif ($try !== null)
                        <a href="{{ $try[1] }}" class="btn-primary btn-lg">{{ $try[0] }} <x-site.icon name="arrow" size="18" /></a>
                        @if (is_string($page['group_start'] ?? null))
                            <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref='.$page['group_start']) }}" class="btn-secondary btn-lg">{{ __('pages.site.group_start') }}</a>
                        @endif
                    @elseif ($page['end']['plus'] ?? false)
                        <a href="/pricing" class="btn-primary btn-lg">{{ __('pages.site.see_plus') }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="{{ $links['start'] }}" class="btn-secondary btn-lg">{{ __('pages.site.start') }}</a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                </p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
