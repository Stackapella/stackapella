{{-- The press kit (/press), in the redesign's sections: who to write to first, Stackapella in a
     sentence and a paragraph, the facts, the logo on light and dark with its downloads, and the
     screenshots from the app (the user guide's, light and dark) on the ink band. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('meta.brand') }}</p>
            <h1 id="page-h">{{ __('site.press.headline') }}</h1>
            <p class="s-lede">{{ __('site.press.lead') }}</p>
            <div class="s-actions s-actions-row">
                <a href="mailto:{{ config('site.contact_email') }}" class="btn-primary btn-lg">{{ __('site.press_words.contact') }}: {{ config('site.contact_email') }}</a>
            </div>
        </section>
        <section class="s-wrap s-sec" aria-label="{{ __('site.press.headline') }}">
            <div class="s-press-grid">
                <article class="s-card3d">
                    <h2 class="s-kick">{{ __('site.press_words.one_liner') }}</h2>
                    <p class="s-press-copy">{{ __('site.press.one_liner') }}</p>
                </article>
                <article class="s-card3d">
                    <h2 class="s-kick">{{ __('site.press_words.short') }}</h2>
                    <p class="s-press-copy">{{ __('site.press.short') }}</p>
                </article>
                <article class="s-card3d">
                    <h2 class="s-kick">{{ __('site.press_words.facts') }}</h2>
                    <dl class="s-facts s-facts-2">
                        @foreach ($facts as $label => $value)
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </article>
                <article class="s-card3d">
                    <h2 class="s-kick">{{ __('site.press_words.logo') }}</h2>
                    <div class="s-press-marks"><span class="s-press-on-light">{{ \App\Support\Site\Icons::mark(64) }}</span><span class="s-press-on-dark">{{ \App\Support\Site\Icons::mark(64) }}</span></div>
                    <p class="s-chips"><a href="/icons/brand/stackapella-wordmark-light.svg" download>{{ __('site.press_words.wordmark_light') }}</a><a href="/icons/brand/stackapella-wordmark-dark.svg" download>{{ __('site.press_words.wordmark_dark') }}</a><a href="/icons/brand/stackapella-stacked-light.svg" download>{{ __('site.press_words.stacked_light') }}</a><a href="/icons/brand/stackapella-stacked-dark.svg" download>{{ __('site.press_words.stacked_dark') }}</a><a href="/icons/brand/stackapella-wordmark-light-1200.png" download>{{ __('site.press_words.wordmark_png') }}</a><a href="/icons/icon.svg" download>{{ __('site.press_words.logo_svg') }}</a><a href="/icons/brand/stackapella-mark-1024.png" download>{{ __('site.press_words.mark_png') }}</a><a href="/icons/og-landing.png">{{ __('site.press_words.card') }}</a></p>
                </article>
            </div>
        </section>
        @if ($shots !== [])
            <section class="s-ink" aria-labelledby="shots-h">
                <div class="s-wrap">
                    <h2 id="shots-h" class="s-title">{{ __('site.press_words.screenshots') }}</h2>
                    <p class="s-lede">{{ __('site.press_words.screenshots_lead') }}</p>
                    @foreach ([true, false] as $wide)
                    <ul @class(['s-press-shots', 's-press-wide' => $wide])>
                        @foreach (array_filter($shots, static fn (array $shot): bool => ($shot['files']['light']['width'] > $shot['files']['light']['height']) === $wide) as $shot)
                            <li>
                                <figure>
                                    <img src="{{ $shot['files']['light']['png'] }}" alt="{{ $shot['caption'] }}" width="{{ $shot['files']['light']['width'] }}" height="{{ $shot['files']['light']['height'] }}" loading="lazy" decoding="async">
                                    <figcaption>{{ $shot['caption'] }}</figcaption>
                                </figure>
                                <p class="s-press-shot-links">
                                    <a href="{{ $shot['files']['light']['png'] }}" class="s-lk" download>{{ __('site.press_words.light') }}</a>
                                    <a href="{{ $shot['files']['dark']['png'] }}" class="s-lk" download>{{ __('site.press_words.dark') }}</a>
                                </p>
                            </li>
                        @endforeach
                    </ul>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
    <x-site.footer />
@endsection
