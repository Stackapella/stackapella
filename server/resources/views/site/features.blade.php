{{-- What Stackapella does (/features), from Hillel's Features design in the redesign's sections: the
     head beside the four-voice player, what works today as a bento of tiles (each its drawing of the
     app; choir groups say they need Plus), what's on the way as chips, who it's for as cards, and the
     last word on the band; once the open_source flag is on, "Built in the open". Words: lang/en/site.php. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div class="s-split">
                <div>
                    <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('pages.site.features') }}</p>
                    <h1 id="page-h">{{ __('site.features.index.headline') }}</h1>
                    <p class="s-lede">{{ __('site.features.index.lead') }}</p>
                    <div class="s-actions">
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
                        <p>{{ __('pages.site.start_note') }}</p>
                    </div>
                </div>
                @if ($draw['hero'] !== null)
                    <x-site.mixer :song="$draw['hero']" id="features-mixer" />
                @endif
            </div>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="works-h">
            <h2 id="works-h" class="s-title"><x-site.skin :game="__('pages.landing.game.works')" :serious="__('pages.site.works')" /></h2>
            @include('site._tiles', ['tiles' => $works, 'bento' => true])
        </section>

        @if ($coming !== [])
            <section class="s-wrap s-sec" aria-labelledby="way-h">
                <p class="s-kick"><x-site.skin :game="__('pages.landing.game.way_kick')" :serious="__('pages.site.on_the_way')" /> <span class="s-soon">{{ __('pages.site.all_soon') }}</span></p>
                <h2 id="way-h" class="s-title" style="font-size: clamp(28px, 6vw, 40px)">{{ __('pages.site.on_the_way_lead') }}</h2>
                <ul class="s-chips">
                    @foreach ($coming as $card)
                        <li><a href="{{ $card['url'] }}"><span class="s-who-ic" aria-hidden="true"><x-site.icon :name="str_starts_with($card['pic'], 'icon:') ? substr($card['pic'], 5) : 'spark'" size="16" /></span>{{ $card['name'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($openSource)
            <section class="s-wrap s-sec2" aria-labelledby="open-h">
                <div class="s-two2">
                    <div>
                        <h2 id="open-h" class="s-h2">{{ __('site.features_open.title') }}</h2>
                        <p class="s-sub">{{ __('site.features_open.lead') }}</p>
                    </div>
                    <div>
                        <ul class="s-points">
                            @foreach ($openPoints as $point)
                                <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                            @endforeach
                        </ul>
                        <p class="s-more">
                            <a href="/open-source" class="btn-secondary">{{ __('site.features_open.more') }}</a>
                            <a href="/selfhost" class="btn-ghost">{{ __('site.features_open.selfhost') }}</a>
                        </p>
                    </div>
                </div>
            </section>
        @endif

        @include('site._who', ['whoCards' => true])

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ __('pages.site.final_title') }}</h2>
                <p>{{ __('pages.site.final_body') }}</p>
                <p class="s-final-btns"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
@endsection
