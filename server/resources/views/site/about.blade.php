{{-- About (/about), in the redesign's sections: Hillel's own note on why Stackapella exists (his
     words, signed, approved as is) in a reading column; who made it, his three music apps and two
     old videos (Hillel, 2026-10-30); its promises on the ink band (Hillel: yes);
     the company as facts; that it's open source (once the open_source flag is on); for mudeo's
     singers alone (MudeoArrival), where it came from; and the last word. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead s-abouthead" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('site.about.eyebrow') }}</p>
            <h1 id="page-h">{{ __('site.about.note_heading') }}</h1>
            <p class="s-lede">{{ __('site.about.lead') }}</p>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="page-h">
            <article class="s-letter-note s-letter-read">
                @foreach ($note as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                <p class="s-sig">{{ $signature }}</p>
            </article>
        </section>

        {{-- Who made this (Hillel's words, 2026-10-30): his two companies as plain followed links, his
             three music apps as a short story, and two old videos as player-like cards with our own
             stills that open YouTube in a new tab (no embed, nothing loaded from YouTube). --}}
        <section class="s-wrap s-sec s-maker" aria-labelledby="maker-h">
            <h2 id="maker-h" class="s-title">{{ __('site.about.maker.heading') }}</h2>
            <p class="s-lede s-maker-intro">{{ __('site.about.maker.intro_start') }} <a href="https://invoiceninja.com" rel="noopener" class="s-lk">{{ __('site.about.maker.invoice_ninja') }}</a> {{ __('site.about.maker.intro_and') }} <a href="https://eventschedule.com" rel="noopener" class="s-lk">{{ __('site.about.maker.event_schedule') }}</a>{{ __('site.about.maker.intro_end') }}</p>
            <ol class="s-howsteps s-maker-steps">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.about.maker.steps') as $step)
                    <li class="s-v{{ $loop->iteration }}"><span class="s-disc" aria-hidden="true">{{ $loop->iteration }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p></div></li>
                @endforeach
            </ol>
            @php($videos = ['music_maker_pro' => ['https://www.youtube.com/watch?v=DIYLPU__dmk', '/icons/about/music-maker-pro.jpg'], 'mudeo_show' => ['https://www.youtube.com/watch?v=QFmrgkIwAds', '/icons/about/mudeo-flutter-show.jpg']])
            <ul class="s-maker-videos">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.about.maker.videos') as $key => $video)
                    <li>
                        <a class="s-video" href="{{ $videos[$key][0] }}" target="_blank" rel="noopener" aria-label="{{ __('site.about.maker.watch', ['title' => $video['title']]) }}">
                            <span class="s-video-frame">
                                <img src="{{ $videos[$key][1] }}" width="960" height="540" alt="" loading="lazy">
                                <span class="s-video-play" aria-hidden="true"><x-site.icon name="play" size="30" /></span>
                                <span class="s-video-len">{{ $video['length'] }}</span>
                            </span>
                            <span class="s-video-title">{{ $video['title'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="s-ink" aria-labelledby="promises-h">
            <div class="s-wrap">
                <p class="s-kick">{{ __('site.about.promises_kick') }}</p>
                <h2 id="promises-h" class="s-title">{{ __('site.about.promises_title') }}</h2>
                <ul class="s-ticks s-ticks-2">
                    @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.about.promises') as $promise)
                        <li><x-site.icon name="check" size="18" />{{ $promise }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="company-h">
            <h2 id="company-h" class="s-kick">{{ __('site.about.company_title') }}</h2>
            <dl class="s-card3d s-factcard">
                <div><dt>{{ __('site.about.facts.address') }}</dt><dd>{{ __('site.about.company') }}</dd></div>
                <div><dt>{{ __('site.about.facts.contact') }}</dt><dd><a href="mailto:{{ config('site.contact_email') }}" class="link">{{ config('site.contact_email') }}</a></dd></div>
                <div><dt>{{ __('site.about.facts.press') }}</dt><dd><a href="/press" class="s-arrow" style="margin-top: 0">{{ __('site.about.press') }} <x-site.icon name="arrow" size="16" /></a></dd></div>
            </dl>
        </section>

        @if ($openSource)
            <section class="s-wrap s-sec2" aria-labelledby="open-h">
                <div class="s-two2">
                    <h2 id="open-h" class="s-h2">{{ __('site.about.open_source_title') }}</h2>
                    <div>
                        <p class="s-sub">{{ __('site.about.open_source') }}</p>
                        <p class="s-more"><a href="/open-source" class="btn-secondary">{{ __('site.about.open_source_more') }}</a></p>
                    </div>
                </div>
            </section>
        @endif

        @if ($mudeo !== [])
            <section id="mudeo" class="s-wrap s-sec" aria-labelledby="mudeo-h">
                <div class="s-letter">
                    <div>
                        <p class="s-k">{{ \App\Support\Site\Icons::mark(24) }} {{ __('pages.landing.mudeo.eyebrow') }}</p>
                        <h2 id="mudeo-h">{{ __('site.about.mudeo.heading') }}</h2>
                    </div>
                    <div>
                        @foreach ($mudeo as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                        <p class="s-sig">{{ __('pages.landing.mudeo.signature') }}</p>
                    </div>
                </div>
            </section>
        @endif

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
