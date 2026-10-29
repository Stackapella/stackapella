{{-- A coach's page at /coach/<handle> (CoachPageController, M8): who they are, their voices,
     styles and languages, their rating, what they offer and at what price, their exercise packs,
     what singers say, and Work with them in the app (links.app, carrying their ref). --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="profile">
    @if ($meta['image'] !== null)
        <meta property="og:image" content="{{ $meta['image'] }}">
    @endif
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-hero s-page s-doc-head" aria-labelledby="page-h">
            <p class="s-eyebrow">{{ __('pages.coach.eyebrow') }}</p>
            <div class="s-coach-top s-coach-hero">
                @include('public._avatar', ['coach' => $coach, 'size' => 80])
                <div>
                    <h1 id="page-h" translate="no">{{ $coach['name'] }}</h1>
                    <p class="s-song-kind">@if ($coach['newCoach']){{ __('pages.coach.new') }}@else{{ trans_choice('pages.coach.rating', $coach['rating']['count'], ['average' => $coach['rating']['average'], 'count' => $coach['rating']['count']]) }}@endif</p>
                </div>
            </div>
            <p class="s-lead">{{ $coach['bio'] }}</p>
        </section>
        <section class="s-wrap s-sec s-doc" style="padding-top: 28px" aria-label="{{ $coach['name'] }}">
            <dl class="s-facts">
                @foreach (['voices' => 'voiceTypes', 'styles' => 'styles'] as $label => $key)
                    @if (! empty($coach[$key]))
                        <div><dt>{{ __('pages.coach.'.$label) }}</dt><dd>@foreach ($coach[$key] as $value)<span>{{ ucfirst((string) $value) }}</span>@endforeach</dd></div>
                    @endif
                @endforeach
                @if (! empty($coach['languages']))
                    <div><dt>{{ __('pages.coach.languages') }}</dt><dd>@foreach ($coach['languages'] as $code)<span>{{ \Locale::getDisplayLanguage((string) $code, app()->getLocale()) }}</span>@endforeach</dd></div>
                @endif
            </dl>
            @if ($coach['offerings'] !== [])
                <h2 class="s-h2" style="margin-top: 40px; font-size: 28px">{{ __('pages.coach.offers', ['name' => $coach['name']]) }}</h2>
                <div class="s-sections" style="margin-top: 16px">
                    @foreach ($coach['offerings'] as $offer)
                        <article class="s-step s-v{{ min($loop->iteration, 4) }}">
                            <p class="s-step-k">{{ __('pages.coach.kinds.'.$offer['kind']) }}</p>
                            <h3>{{ $offer['title'] }}</h3>
                            @if ($offer['description'])
                                <p>{{ $offer['description'] }}</p>
                            @endif
                            <p class="s-price" style="font-size: 30px">{{ \App\Support\Site\Money::format((int) $offer['priceCents'], (string) $offer['currency']) }}</p>
                            <p class="s-note">{{ collect([
                                $offer['turnaroundDays'] !== null ? __('pages.coach.turnaround', ['days' => $offer['turnaroundDays']]) : null,
                                $offer['durationMinutes'] !== null ? __('pages.coach.duration', ['minutes' => $offer['durationMinutes']]) : null,
                                $offer['reviewsPerMonth'] !== null ? __('pages.coach.per_month', ['count' => $offer['reviewsPerMonth']]) : null,
                                $offer['maxSeconds'] !== null ? __('pages.coach.max', ['seconds' => $offer['maxSeconds']]) : null,
                            ])->filter()->join(' · ') }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
            @if ($coach['packs'] !== [])
                <h2 class="s-h2" style="margin-top: 40px; font-size: 28px">{{ __('pages.coach.packs') }}</h2>
                <ul class="s-cards">
                    @foreach ($coach['packs'] as $pack)
                        <li><div class="s-card"><b>{{ $pack['title'] }}</b><span>{{ $pack['description'] }}</span><span>{{ \App\Support\Site\Money::format((int) $pack['priceCents'], (string) $pack['currency']) }}</span></div></li>
                    @endforeach
                </ul>
            @endif
            @if ($coach['ratings'] !== [])
                <h2 class="s-h2" style="margin-top: 40px; font-size: 28px">{{ __('pages.coach.ratings') }}</h2>
                <div class="s-stories">
                    @foreach ($coach['ratings'] as $rating)
                        <figure class="s-story">
                            <p class="s-note" aria-label="{{ __('pages.coach.stars', ['stars' => $rating['stars']]) }}"><span aria-hidden="true">{{ str_repeat('★', (int) $rating['stars']) }}{{ str_repeat('☆', 5 - (int) $rating['stars']) }}</span></p>
                            @if ($rating['text'])
                                <blockquote>{{ $rating['text'] }}</blockquote>
                            @endif
                            <figcaption translate="no">{{ $rating['singer']['name'] }}</figcaption>
                            @if ($rating['reply'])
                                <p class="s-note">{{ __('pages.coach.reply', ['name' => $coach['name']]) }}: {{ $rating['reply'] }}</p>
                            @endif
                        </figure>
                    @endforeach
                </div>
            @endif
            <div class="s-step s-v2 s-try">
                <p>{{ __('pages.coach.start_note') }}</p>
                <p class="s-share-btns"><a href="{{ $links['app'] }}" class="btn-primary btn-lg">{{ __('pages.coach.start', ['name' => $coach['name']]) }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
