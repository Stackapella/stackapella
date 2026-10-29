{{-- The coach directory at /coaches (CoachesPageController, N4, behind coach_directory): each
     approved coach as a card, featured ones marked, with their voices, rating and starting price. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header />
    <main>
        @include('site._hero', ['headline' => __('pages.coaches.headline'), 'lead' => __('pages.coaches.lead')])
        <section class="s-wrap s-sec" style="padding-top: 24px" aria-label="{{ __('pages.coaches.headline') }}">
            @if ($coaches === [])
                <p class="s-cur">{{ __('pages.coaches.none') }}</p>
            @else
                <ul class="s-cards">
                    @foreach ($coaches as $coach)
                        <li>
                            <a href="/coach/{{ $coach['handle'] }}" class="s-card s-coach-card">
                                <span class="s-coach-top">
                                    @include('public._avatar', ['coach' => $coach, 'size' => 48])
                                    <span><b translate="no">{{ $coach['name'] }}</b>
                                        <span>@if ($coach['newCoach']){{ __('pages.coach.new') }}@else{{ trans_choice('pages.coach.rating', $coach['rating']['count'], ['average' => $coach['rating']['average'], 'count' => $coach['rating']['count']]) }}@endif</span>
                                    </span>
                                    @if ($coach['featured'])
                                        <span class="s-badge s-badge-free">{{ __('pages.coaches.featured') }}</span>
                                    @endif
                                </span>
                                <span>{{ $coach['bio'] }}</span>
                                <span>{{ collect([...$coach['voiceTypes'], ...$coach['styles']])->map(fn ($v) => ucfirst((string) $v))->join(' · ') }}</span>
                                @if ($coach['from'] !== null)
                                    <span>{{ __('pages.coaches.from', ['price' => \App\Support\Site\Money::format((int) $coach['from']['cents'], (string) $coach['from']['currency'])]) }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
    <x-site.footer />
@endsection
