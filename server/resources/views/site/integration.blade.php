{{-- One integration (/integrations/<slug>; IntegrationPagesController): the other app and Stackapella, for the
     people who'd use both. What each does today, side by side (solid), how to use them together now, and what the
     connection is being built to do (dashed, under Coming: none of it is open). Event Schedule's own page draws the
     same rule, solid today and dashed being built. Words: lang/en/site.php integrations. --}}
@extends('public.layout')

@section('arcade')
@endsection

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="int">
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <x-site.crumbs :trail="[[__('site.integrations.crumb'), '/integrations'], [$page['name']]]" :ld="true" />
            <h1 id="page-h">{{ $page['headline'] }}</h1>
            <p class="s-lead">{{ $page['lead'] }}</p>
            <div class="s-actions">
                <a href="{{ $page['site'] }}" class="btn-primary btn-lg" rel="noopener">{{ $page['visit'] }} <x-site.icon name="arrow" size="18" /></a>
                @isset($page['partner'])
                    <a href="{{ $page['partner'] }}" class="btn-secondary btn-lg" rel="noopener">{{ $page['partner_link'] }}</a>
                @endisset
            </div>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="today-h">
            <h2 id="today-h" class="int-h2">{{ $page['today_title'] }}</h2>
            <p class="int-sub">{{ $page['today_lead'] }}</p>
            <ul class="int-legend">
                <li class="int-key-today">{{ __('site.integrations.legend_today') }}</li>
                <li class="int-key-coming">{{ __('site.integrations.legend_coming') }}</li>
            </ul>
            <div class="int-duet">
                @foreach (['them', 'us'] as $side)
                    <div @class(['int-half', 'int-us' => $side === 'us'])>
                        <p class="int-tag int-tag-today">{{ __('site.integrations.today') }}</p>
                        <h3>{{ $page[$side.'_title'] }}</h3>
                        <ul class="s-ticks">
                            @foreach ((array) $page[$side] as $line)
                                <li><x-site.icon name="check" size="18" />{{ $line }}</li>
                            @endforeach
                        </ul>
                        @isset($page[$side.'_note'])
                            <p class="int-note">{{ $page[$side.'_note'] }}</p>
                        @endisset
                    </div>
                @endforeach
            </div>
        </section>

        @isset($page['how'])
            <section class="s-wrap s-sec" aria-labelledby="how-h">
                <h2 id="how-h" class="int-h2">{{ $page['how_title'] }}</h2>
                <ol class="int-steps">
                    @foreach ((array) $page['how'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </section>
        @endisset

        @isset($page['tickets'])
            <section class="s-wrap s-sec" aria-labelledby="tickets-h">
                <div class="int-aside">
                    <p class="int-tag int-tag-today">{{ __('site.integrations.today') }}</p>
                    <h2 id="tickets-h">{{ $page['tickets_title'] }}</h2>
                    <p>{{ $page['tickets'] }}</p>
                    <a href="/integrations/event-schedule" class="s-lk">{{ $page['tickets_link'] }} <x-site.icon name="arrow" size="16" /></a>
                </div>
            </section>
        @endisset

        <section class="s-wrap s-sec" aria-labelledby="coming-h">
            <div class="int-coming">
                <p class="int-tag int-tag-coming">{{ __('site.integrations.coming') }}</p>
                <h2 id="coming-h" class="int-h2">{{ $page['coming_title'] }}</h2>
                <p class="int-sub">{{ $page['coming_lead'] }}</p>
                @foreach ((array) $page['coming'] as [$who, $items])
                    <h3 class="int-who">{{ $who }}</h3>
                    <ul class="int-cards">
                        @foreach ((array) $items as [$heading, $body])
                            <li><b>{{ $heading }}</b><span>{{ $body }}</span></li>
                        @endforeach
                    </ul>
                @endforeach
                <p class="int-note">{{ $page['connect_note'] }}</p>
            </div>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <h2 id="faq-h" class="int-h2">{{ __('site.integrations.faq_title') }}</h2>
            @include('site.courses._faq')
        </section>

        @php($other = collect(\App\Support\Site\SitePages::INTEGRATIONS)->first(fn (string $slug): bool => $slug !== $page['slug']))
        @if ($other !== null)
            <section class="s-wrap s-sec" aria-labelledby="other-h">
                <h2 id="other-h" class="int-who">{{ __('site.integrations.more_title') }}</h2>
                <ul class="int-hub int-hub-one">
                    @include('site._integration-card', ['slug' => $other, 'item' => (array) __('site.integrations.pages.'.$other)])
                </ul>
            </section>
        @endif
    </main>
    <x-site.footer />
@endsection
