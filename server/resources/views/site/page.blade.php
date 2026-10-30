{{-- A feature page (/features/<slug>), in the redesign's sections (docs/marketing-redesign-plan.md,
     after Hillel's Features design): where you are, the headline and Start singing beside the mixer
     (where hearing it helps) or the feature's own drawing on a raised card; then each section with a
     drawing as words beside it, alternating sides, the last of them on the ink band; the sections
     without one as compact details; three features that go well with it; and the page's own last
     word on the band. "Coming soon" stands by the headline while the feature isn't open
     (config/site.php). Words: lang/en/site.php. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    {{-- A feature with its own designed page (site/features/<slug>) uses that instead. --}}
    @if (view()->exists('site.features.'.$page['slug']))
        @include('site.features.'.$page['slug'])
    @else
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div @class(['s-split', 's-solo' => $hero === null && empty($page['pic'])])>
                <div>
                    <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}">
                        <a href="/features">{{ __('pages.site.features') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] ?? $page['headline'] }}</span>
                    </nav>
                    @if ($page['soon'])
                        <p class="s-soonpill"><x-site.soon /></p>
                    @endif
                    <h1 id="page-h">{{ $page['headline'] }}</h1>
                    <p class="s-lede"><x-site.say :text="$page['lead']" /></p>
                    @if ($page['soon'] && $page['soon_line'] !== null)
                        <p class="s-soonline">{{ $page['soon_line'] }}</p>
                    @endif
                    <div class="s-actions">
                        {{-- A feature still coming leads to what works today (its `try`), else Start singing. --}}
                        @if ($page['soon'] && is_array($page['try'] ?? null))
                            <a href="{{ $links[$page['try'][1]] ?? $page['try'][1] }}" class="btn-primary btn-lg">{{ $page['try'][0] }} <x-site.icon name="arrow" size="18" /></a>
                        @else
                            <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
                            <p>{{ __('pages.site.start_note') }}</p>
                        @endif
                    </div>
                </div>
                @if ($hero !== null)
                    <x-site.mixer :song="$hero" id="page-mixer" />
                @elseif (! empty($page['shot']) && \App\Support\Site\HelpGuide::shot($page['shot']) !== null)
                    <div class="s-card3d s-heroshot"><x-site.appshot :id="$page['shot']" :alt="$page['shot_alt'] ?? ''" /></div>
                @elseif (str_starts_with((string) ($page['pic'] ?? ''), 'icon:'))
                    {{-- A feature still coming has no screen to draw yet: its icon in a gem over the four voices. --}}
                    <div class="s-card3d s-iconhero" aria-hidden="true">
                        <span class="gem"><x-site.icon :name="substr($page['pic'], 5)" size="44" /></span>
                        <span class="s-stackbig">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                    </div>
                @elseif (! empty($page['pic']))
                    <div class="s-card3d s-heropic"><x-site.pic :name="$page['pic']" :hero="$draw['hero']" :pics="$draw['pics']" /></div>
                @endif
            </div>
        </section>

        {{-- The sections with a drawing: words beside it, alternating sides; the last on the ink band. --}}
        @foreach ($page['rows'] as $section)
            @php($band = $loop->last && $loop->count > 1)
            <section @class(['s-ink' => $band, 's-sec' => ! $band]) aria-labelledby="row-{{ $loop->iteration }}">
                <div @class(['s-wrap', 's-split', 's-flip' => ! $band && $loop->even])>
                    <div>
                        <h2 id="row-{{ $loop->iteration }}" class="s-rowtitle"><x-site.say :text="$section['heading']" /></h2>
                        <p class="s-lede"><x-site.say :text="$section['body']" /></p>
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
                    </div>
                    {{-- A row whose picture is a game feature (stars) shows another in serious mode (`serious_pic`). --}}
                    <div @class(['s-card3d', 's-skin-g' => isset($section['serious_pic'])])><x-site.pic :name="$section['pic']" :hero="$draw['hero']" :pics="$draw['pics']" /></div>
                    @isset($section['serious_pic'])
                        <div class="s-card3d s-skin-s"><x-site.pic :name="$section['serious_pic']" :hero="$draw['hero']" :pics="$draw['pics']" /></div>
                    @endisset
                </div>
            </section>
        @endforeach

        {{-- The sections without a drawing: compact details, two or three a row. --}}
        @if ($page['notes'] !== [])
            <section class="s-wrap s-sec" aria-label="{{ $page['headline'] }}">
                <div class="s-details">
                    @foreach ($page['notes'] as $section)
                        <article class="s-v{{ $loop->index % 4 + 1 }}">
                            <h2><i class="gem" aria-hidden="true"></i><x-site.say :text="$section['heading']" /></h2>
                            <p><x-site.say :text="$section['body']" /></p>
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

        @if ($cards !== [])
            <section class="s-wrap s-sec" aria-labelledby="cards-h">
                <div class="s-headrow">
                    <h2 id="cards-h" class="s-kick">{{ __('pages.site.goes_with') }}</h2>
                    <a href="/features" class="s-arrow">{{ __('pages.site.all_features') }} <x-site.icon name="arrow" size="16" /></a>
                </div>
                @include('site._tiles', ['tiles' => array_slice($cards, 0, 3)])
            </section>
        @endif

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ $page['end']['title'] ?? __('pages.site.final_title') }}</h2>
                <p>{{ $page['end']['body'] ?? __('pages.site.final_body') }}</p>
                <p class="s-final-btns">
                    @if ($page['soon'] && is_array($page['try'] ?? null))
                        <a href="{{ $links[$page['try'][1]] ?? $page['try'][1] }}" class="btn-primary btn-lg">{{ $page['try'][0] }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                </p>
            </div>
        </section>
    </main>
    @endif
    <x-site.footer />
@endsection

@section('scripts')
    @if ($hero !== null)
        <script type="module" src="/js/public/mixer.js"></script>
    @endif
@endsection
