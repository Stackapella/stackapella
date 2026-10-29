{{-- How it works (/how-it-works; Hillel: "pricing and help have their own pages but how it works
     doesn't ... make them consistent"): the landing's four steps opened out. The head with a real Sing
     screen; the four voices, four steps (the landing's own section); the first lesson from Start to a
     harmony; the four voices to hear (the hero's mixer, when the song is in the library); what you
     need; and the last word. Both skins. Words: lang/en/site.php `how`. --}}
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
                    <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('site.how.eyebrow') }}</p>
                    <h1 id="page-h">{{ __('site.how.headline') }}</h1>
                    <p class="s-lede">{{ __('site.how.lead') }}</p>
                    <div class="s-actions s-actions-row">
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg" data-funnel-start>{{ __('pages.landing.start_free') }} <x-site.icon name="arrow" size="18" /></a>
                        @if ($hero !== null)
                            <a href="#hear" class="btn-secondary btn-lg">{{ __('pages.landing.hear') }}</a>
                        @endif
                    </div>
                </div>
                <div class="s-card3d s-heroshot"><x-site.appshot id="learn-a-part--notes" :alt="__('site.how.shot_alt')" /></div>
            </div>
        </section>

        @include('pages.landing._steps')

        <section class="s-wrap s-sec" aria-labelledby="first-h">
            <p class="s-kick">{{ __('site.how.first.kick') }}</p>
            <h2 id="first-h" class="s-title">{{ __('site.how.first.title') }}</h2>
            <ol class="s-howsteps">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.how.first.steps') as [$step, $body])
                    <li class="s-v{{ $loop->iteration }}"><span class="s-disc" aria-hidden="true">{{ $loop->iteration }}</span><div><h3>{{ $step }}</h3><p>{{ $body }}</p></div></li>
                @endforeach
            </ol>
        </section>

        @if ($hero !== null)
            <section id="hear" class="s-ink" aria-labelledby="hear-h">
                <div class="s-wrap s-sec s-split s-howhear">
                    <div>
                        <p class="s-kick">{{ __('site.how.hear.kick') }}</p>
                        <h2 id="hear-h" class="s-title">{{ __('site.how.hear.title') }}</h2>
                        <p class="s-lede">{{ __('site.how.hear.lead') }}</p>
                        <ul class="s-ticks">
                            @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.how.hear.points') as $point)
                                <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <x-site.mixer :song="$hero" id="hear-mixer">
                        <p class="s-mix-hint">{{ __('pages.landing.hero.hint') }}</p>
                    </x-site.mixer>
                </div>
            </section>
        @endif

        <section class="s-wrap s-sec" aria-labelledby="need-h">
            <p class="s-kick">{{ __('site.how.need.kick') }}</p>
            <h2 id="need-h" class="s-title">{{ __('site.how.need.title') }}</h2>
            <ul class="s-ticks s-ticks-2">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.how.need.points') as $point)
                    <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                @endforeach
            </ul>
            <a href="/help" class="s-arrow">{{ __('site.how.need.more') }} <x-site.icon name="arrow" size="16" /></a>
        </section>

        <section class="s-ink" aria-labelledby="final-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <p class="s-kick s-skin-g s-player">{{ __('pages.landing.game.final_kick') }}</p>
                <h2 id="final-h">{{ __('pages.landing.final_title') }}</h2>
                <p>{{ __('pages.landing.final_body') }}</p>
                <p class="s-final-btns">
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg" data-funnel-start>{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a>
                </p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    {{-- Start says it's the landing's (from=start); no experiment arm is drawn here. --}}
    <script type="application/json" data-funnel>@json(['counts' => false, 'experiment' => null])</script>
    <script type="module" src="/js/public/funnel.js"></script>
@endsection
