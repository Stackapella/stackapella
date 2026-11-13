{{-- The courses (/courses), in Hillel's design with filters (2026-11-13, designs/courses-filters-1113): the head beside a
     Start here card for the beginner course; a sticky row of filter chips (All and each group, with their counts) and a
     search, which filter the cards in place (js/public/courses-filter.js; the chip remembered in this browser only); each
     group numbered, its heading and blurb beside its cards (a dot in the group's colour, the name to the course's page, one
     line, Try lesson 1 into the app; a course not open yet dashed and Soon); then Not sure where to begin. Every count,
     course and Soon from the catalogue (CoursePagesController::index), never the mockup. Words: lang/en/site.php `courses`. --}}
@extends('public.layout', ['title' => $meta['title']])

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-crs-head" aria-labelledby="page-h">
            <div>
                <p class="s-eyebrow"><x-site.skin :game="__('pages.landing.game.courses_kick')" :serious="__('site.courses.index.kicker')" /></p>
                <h1 id="page-h"><x-site.skin :game="__('pages.landing.game.courses_title')" :serious="__('site.courses.index.headline')" /></h1>
                <p class="s-lede">{{ __('site.courses.index.lead_count', ['count' => $total]) }}</p>
            </div>
            <div class="s-crs-start">
                <p class="s-crs-start-k">{{ __('site.courses.index.start_kick') }}</p>
                <h2 class="s-crs-start-h"><a href="/features/course">{{ __('site.courses.index.start_title') }}<br>{{ __('site.courses.index.start_title_2') }}</a></h2>
                <p>{{ __('site.courses.index.start_body') }}</p>
                <span class="s-crs-start-bar" aria-hidden="true">@for ($i = 0; $i < $beginner['units']; $i++)<b class="s-v{{ intdiv($i, 2) % 4 + 1 }}"></b>@endfor</span>
                <div class="s-crs-start-go">
                    <a href="{{ $beginner['try'] }}" class="btn-primary btn-lg">{{ __('site.courses.index.start_button') }} <x-site.icon name="arrow" size="18" /></a>
                    <span class="s-crs-start-meta">{{ __('site.courses.index.start_meta', ['units' => $beginner['units'], 'lessons' => $beginner['lessons']]) }}</span>
                </div>
            </div>
        </section>

        {{-- The filters: one chip pressed at a time, and a search; both narrow the cards below, nothing reloads. --}}
        <div class="s-crs-bar" data-courses-filter>
            <div class="s-wrap s-crs-bar-in">
                <div class="s-crs-chips" role="group" aria-label="{{ __('site.courses.index.filter_label') }}">
                    <button type="button" class="s-crs-chip" data-filter="all" aria-pressed="true">{{ __('pages.site.courses_all') }} <span class="s-crs-n">{{ $total }}</span></button>
                    @foreach ($groups as $key => $group)
                        <button type="button" class="s-crs-chip s-cg-{{ $key }}" data-filter="{{ $key }}" aria-pressed="false"><i aria-hidden="true"></i>{{ $group['name'] }} <span class="s-crs-n">{{ count($group['cards']) }}</span></button>
                    @endforeach
                </div>
                <label class="s-crs-search">
                    <span class="sr-only">{{ __('site.courses.index.search_label') }}</span>
                    <x-site.icon name="search" size="16" />
                    <input type="search" data-courses-search placeholder="{{ __('site.courses.index.search_placeholder') }}" autocomplete="off" enterkeyhint="search">
                </label>
            </div>
            <p class="sr-only" aria-live="polite" data-courses-shown data-words="{{ __('site.courses.index.shown') }}"></p>
        </div>

        @foreach ($groups as $key => $group)
            <section class="s-wrap s-crs-group s-cg-{{ $key }}" aria-labelledby="group-{{ $key }}" data-course-group="{{ $key }}">
                <div class="s-crs-group-head">
                    <p class="s-crs-num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                    <h2 id="group-{{ $key }}">{{ $group['name'] }}</h2>
                    @if ($group['blurb'] !== '')
                        <p class="s-crs-blurb">{{ $group['blurb'] }}</p>
                    @endif
                    <p class="s-crs-meta">{{ trans_choice('site.courses.index.group_meta', count($group['cards']), ['count' => count($group['cards'])]) }}@if ($group['soon'] > 0) · {{ __('site.courses.index.group_soon', ['count' => $group['soon']]) }}@endif</p>
                </div>
                <ul class="s-crs-cards">
                    @foreach ($group['cards'] as $card)
                        <li @class(['s-crs-card', 's-crs-soon' => ! $card['open']]) data-course-card data-text="{{ mb_strtolower($card['name'].' '.$card['summary']) }}">
                            <span class="s-crs-dot" aria-hidden="true"></span>
                            @unless ($card['open'])
                                <span class="s-crs-soon-tag">{{ __('site.courses.index.soon') }}</span>
                            @endunless
                            <h3><a href="{{ $card['url'] }}">{{ $card['name'] }}</a></h3>
                            <p>{{ $card['summary'] }}</p>
                            @if ($card['try'] !== null)
                                <a href="{{ $card['try'] }}" class="s-crs-try">{{ __('site.courses.index.try') }}<span class="sr-only">: {{ $card['name'] }}</span> <x-site.icon name="arrow" size="16" /></a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        {{-- Nothing matches the search: say so, and one way back. --}}
        <div class="s-wrap s-crs-empty" data-courses-empty hidden>
            <p data-words="{{ __('site.courses.index.empty', ['q' => ':q']) }}"></p>
            <button type="button" class="btn-secondary" data-courses-reset>{{ __('site.courses.index.show_all') }}</button>
        </div>

        <section class="s-crs-close" aria-labelledby="close-h">
            <div class="s-wrap s-crs-close-in">
                <div>
                    <h2 id="close-h">{{ __('site.courses.index.close_title') }}</h2>
                    <p>{{ __('site.courses.index.close_body') }}</p>
                </div>
                <a href="{{ $beginner['try'] }}" class="btn-primary btn-lg">{{ __('site.courses.index.close_button') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/courses-filter.js"></script>
@endsection
