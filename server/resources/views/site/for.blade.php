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

{{-- The coaches' button: while coaching is coming, write to us; once coach sign-up opens (the
     coach_signup flag; the directory stays hidden), apply in the app. --}}
@php($signup = $page['slug'] === 'coaches' && app(\App\Support\Flags::class)->on('coach_signup'))
@php($try = is_array($page['try'] ?? null) && \App\Support\Site\SitePages::soon('coaching') ? $page['try'] : null)
@php($try = $signup ? [__('pages.site.coach_apply'), \App\Support\Http\Hosts::app('/coach/apply')] : $try)

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div @class(['s-split', 's-solo' => empty($page['pic'])])>
                <div>
                    <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/features#who-h">{{ __('pages.site.who_crumb') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                    @if ($signup)
                        <p class="s-soonpill"><span class="s-soon s-open">{{ __('pages.site.coach_signup_pill') }}</span></p>
                    @elseif ($page['slug'] === 'coaches' && \App\Support\Site\SitePages::soon('coaching'))
                        <p class="s-soonpill"><x-site.soon /></p>
                    @endif
                    <h1 id="page-h">{{ $page['headline'] }}</h1>
                    <p class="s-lede">{{ $page['lead'] }}</p>
                    @if ($signup)
                        <p class="s-soonline">{{ __('pages.site.coach_signup_line') }}</p>
                    @endif
                    <div class="s-actions s-actions-row">
                        @if ($try !== null)
                            <a href="{{ $try[1] }}" class="btn-primary btn-lg">{{ $try[0] }} <x-site.icon name="arrow" size="18" /></a>
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

        @if ($cards !== [])
            <section class="s-wrap s-sec" aria-labelledby="cards-h">
                <div class="s-headrow">
                    <h2 id="cards-h" class="s-kick">{{ __('pages.site.uses') }}</h2>
                    <a href="/features" class="s-arrow">{{ __('pages.site.all_features') }} <x-site.icon name="arrow" size="16" /></a>
                </div>
                @include('site._tiles', ['tiles' => array_slice($cards, 0, 3)])
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
                    @if ($try !== null)
                        <a href="{{ $try[1] }}" class="btn-primary btn-lg">{{ $try[0] }} <x-site.icon name="arrow" size="18" /></a>
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
