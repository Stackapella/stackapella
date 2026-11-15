{{-- /next-chatgpt: a candidate landing page under test beside / (App\Support\Pages\LandingNextChatgpt; Hillel, 2026-11-15:
     "a full screen immersive experience ... starting from scratch with a CSS heavy amazing design"). The whole page is
     one stage. Its own document, sheet (css/landing-next-chatgpt.css, written by hand, no Tailwind) and script, sharing
     nothing of the site's look, so the site's pages can't change it and it can't change them. Calm only for now (Hillel:
     "just do light/dark serious no game mode until we decide"): game mode sees the same page.

     The song is played by the song page's player, js/public/mixer.js, unchanged: the stage carries its hooks (data-player,
     data-song, data-play, data-voice with data-hear-alone and data-pick, data-beat and data-length on every note and
     word, data-time), so the recording, a voice heard alone and the loading states are that tested code. The notes move
     by the player's own --p in CSS. The first frame is all here, without a script. --}}
@php($steps = ['hear', 'along', 'record', 'share'])
@php($home = $language === 'en' ? '/' : '/'.$language)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- A test page: never in search (the response says so too), with no canonical and no other-language links. --}}
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#050407">
    @if (config('hosts.pass_domain'))
        <meta name="theme-cookie-domain" content="{{ config('hosts.pass_domain') }}">
    @endif
    @php($web = (string) config('app.web_path'))
    <link rel="preload" as="font" type="font/woff2" href="/fonts/figtree-latin.woff2" crossorigin>
    {{-- Light or dark as chosen anywhere on the site, before the page paints. --}}
    <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/theme-boot.js') }}"></script>
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url($web, '/css/landing-next-chatgpt.css') }}">
</head>
<body class="lnc" data-lnc-scene="0">
    <a class="lnc-skip" href="#lnc-stage">{{ __('next_chatgpt.skip') }}</a>

    {{-- The header (Hillel, 2026-11-15): slim and see-through over the stage; it shrinks on the way down and comes back on
         the way up (the script; without it, it stays). --}}
    <header class="lnc-top" data-lnc-top>
        <a class="lnc-logo" href="{{ $home }}" aria-label="{{ __('next_chatgpt.nav.label') }}">{{ \App\Support\Site\Icons::mark(28) }}<span translate="no">{{ __('next_chatgpt.nav.label') }}</span></a>
        <nav class="lnc-nav" aria-label="{{ __('next_chatgpt.nav.label') }}">
            @foreach (['songs', 'courses', 'tools', 'pricing'] as $page)
                <a href="{{ $links[$page] }}">{{ __('next_chatgpt.nav.'.$page) }}</a>
            @endforeach
        </nav>
        <div class="lnc-top-end">
            <button type="button" class="lnc-theme" data-lnc-theme hidden aria-label="{{ __('next_chatgpt.nav.to_light') }}" data-label-light="{{ __('next_chatgpt.nav.to_light') }}" data-label-dark="{{ __('next_chatgpt.nav.to_dark') }}"><span class="lnc-i-sun"><x-site.icon name="sun" size="18" /></span><span class="lnc-i-moon"><x-site.icon name="moon" size="18" /></span></button>
            <span class="lnc-lang" role="group" aria-label="{{ __('next_chatgpt.nav.language') }}">
                @foreach ($languages as $choice)
                    <a href="{{ $choice['url'] }}" lang="{{ $choice['code'] }}" translate="no" @if ($choice['current']) aria-current="true" @endif>{{ mb_strtoupper($choice['code']) }}<span class="lnc-sr"> {{ $choice['name'] }}</span></a>
                @endforeach
            </span>
            <a class="lnc-open" href="{{ $links['app'] }}">{{ __('next_chatgpt.nav.open') }}</a>
        </div>
    </header>

    <main class="lnc-main">
        {{-- The film: the stage stays on screen (sticky) while its five scenes pass over it. --}}
        <div class="lnc-film" id="lnc-stage" data-lnc-film data-scene="0" @if ($stage !== null) data-player data-focus="{{ \App\Support\Pages\LandingNextChatgpt::FOCUS }}" style="--beats: {{ $stage['beats'] }}" @endif>
            @if ($stage !== null)
                <script type="application/json" data-song>@json($stage['song'])</script>
                <script type="application/json" data-lnc-learn>@json($stage['learn'])</script>
            @endif
            <div class="lnc-pin">
                <div class="lnc-sky" aria-hidden="true"></div>
                @if ($stage !== null)
                    {{-- Play: one button. Large on the playhead until it's pressed; then it lives in the dock, which stays
                         at the foot of every scene, so the song follows the visitor down the page. --}}
                    <div class="lnc-dock" role="group" aria-label="{{ __('next_chatgpt.stage.label', ['song' => $stage['song']['title']]) }}">
                        <button type="button" class="lnc-play" data-play aria-label="{{ __('next_chatgpt.stage.play', ['song' => $stage['song']['title']]) }}" data-label-play="{{ __('next_chatgpt.stage.play', ['song' => $stage['song']['title']]) }}" data-label-stop="{{ __('next_chatgpt.stage.stop') }}">
                            <span class="lnc-play-disc" aria-hidden="true"><span class="lnc-i-play"><x-site.icon name="play" size="26" /></span><span class="lnc-i-stop"><x-site.icon name="stop" size="22" /></span></span>
                            <span class="lnc-play-lbl" aria-hidden="true"><b>{{ __('next_chatgpt.stage.play', ['song' => $stage['song']['title']]) }}</b><small>{{ __('next_chatgpt.stage.note', ['length' => $stage['length']]) }}</small></span>
                        </button>
                        <span class="lnc-time" data-time>0:00 / {{ $stage['length'] }}</span>
                        <span class="lnc-chips" role="group" aria-label="{{ __('next_chatgpt.stage.hear_one') }}">
                            @foreach ($stage['lanes'] as $lane)
                                <button type="button" class="lnc-chip lnc-v{{ $lane['color'] }}" data-lnc-chip="{{ $lane['key'] }}" aria-pressed="false" aria-label="{{ __('next_chatgpt.stage.hear_only', ['part' => mb_strtolower($lane['name'])]) }}"><i aria-hidden="true">{{ mb_substr($lane['name'], 0, 1) }}</i><span aria-hidden="true">{{ $lane['name'] }}</span></button>
                            @endforeach
                        </span>
                        <a class="lnc-go" href="{{ $links['start'] }}">{{ __('next_chatgpt.start') }} <x-site.icon name="arrow" size="16" /></a>
                    </div>
                    {{-- The sung line, large, while it plays: the line whose word is lit shows (CSS :has). --}}
                    <p class="lnc-words" aria-hidden="true" translate="no">@foreach ($stage['lines'] as $line)<span class="lnc-line">@foreach ($line as $word)<span data-beat="{{ $word['beat'] }}" data-length="{{ $word['length'] }}">{{ $word['text'] }}</span>{{ $word['joins'] ? '' : ' ' }}@endforeach</span>@endforeach</p>

                    <div class="lnc-lanes" role="radiogroup" aria-label="{{ __('next_chatgpt.stage.voices') }}">
                        <span class="lnc-playhead" aria-hidden="true"></span>
                        @foreach ($stage['lanes'] as $lane)
                            @php($part = mb_strtolower($lane['name']))
                            <div @class(['lnc-lane', 'lnc-v'.$lane['color'], 'lnc-is-focus' => $lane['key'] === \App\Support\Pages\LandingNextChatgpt::FOCUS]) data-voice="{{ $lane['key'] }}" style="--w: {{ [100, 74, 88, 62][$loop->index % 4] }}%">
                                {{-- The whole lane: hear this voice alone; again, all four (the player's own control). --}}
                                <button type="button" class="lnc-tap" data-hear-alone aria-pressed="false" aria-label="{{ __('next_chatgpt.stage.hear_only', ['part' => $part]) }}" data-label="{{ __('next_chatgpt.stage.hear_only', ['part' => $part]) }}" data-label-back="{{ __('next_chatgpt.stage.hear_all') }}"></button>
                                <div class="lnc-lane-head" aria-hidden="true">
                                    <b><span class="lnc-name">{{ $lane['name'] }}</span><span class="lnc-you">{{ __('next_chatgpt.steps.record.you') }}</span></b>
                                    <span class="lnc-meter"><u></u><u></u><u></u><u></u></span>
                                    @if ($lane['real'])
                                        <span class="lnc-tag lnc-tag-real">{{ __('next_chatgpt.stage.real') }}</span>
                                    @endif
                                    <span class="lnc-tag lnc-tag-alone">{{ __('next_chatgpt.stage.alone') }}</span>
                                    <span class="lnc-tag lnc-tag-trace">{{ __('next_chatgpt.steps.along.trace') }}</span>
                                    <span class="lnc-tag lnc-tag-take">{{ __('next_chatgpt.steps.record.take') }}</span>
                                </div>
                                <div class="lnc-track" aria-hidden="true" translate="no">
                                    @foreach ($lane['gems'] as $gem)
                                        <i class="lnc-gem" data-beat="{{ $gem['beat'] }}" data-length="{{ $gem['length'] }}" style="--b: {{ $gem['beat'] }}; --l: {{ $gem['length'] }}; --y: {{ $gem['y'] }}">{{ $gem['text'] }}</i>
                                    @endforeach
                                </div>
                                {{-- A singer's pitch over the notes, as Sing draws it: a picture, shown in the story's scenes. --}}
                                <div class="lnc-trace" aria-hidden="true"><svg viewBox="0 0 {{ $stage['beats'] }} 1" preserveAspectRatio="none"><path d="@foreach ($lane['gems'] as $gem){{ $loop->first ? 'M' : 'L' }}{{ round($gem['beat'] + 0.1, 2) }} {{ $gem['y'] }} L{{ round($gem['beat'] + $gem['length'] - 0.12, 2) }} {{ $gem['y'] }} @endforeach"/></svg></div>
                                <button type="button" class="lnc-mine" role="radio" aria-checked="false" tabindex="{{ $loop->first ? '0' : '-1' }}" data-pick aria-label="{{ __('next_chatgpt.stage.mine_label', ['part' => $part]) }}" data-label="{{ __('next_chatgpt.stage.mine_label', ['part' => $part]) }}" data-label-you="{{ __('next_chatgpt.stage.your_part', ['part' => $part]) }}"><span class="lnc-mine-off">{{ __('next_chatgpt.stage.mine') }}</span><span class="lnc-mine-on">{{ __('next_chatgpt.stage.mine_on') }}</span></button>
                            </div>
                        @endforeach
                    </div>
                    {{-- The share scene's picture: the song stacked, and the two ways to send it. --}}
                    <div class="lnc-sharecard" aria-hidden="true">
                        <p><b translate="no">{{ __('next_chatgpt.steps.share.card', ['song' => $stage['song']['title']]) }}</b><span>{{ __('next_chatgpt.steps.share.voices', ['count' => count($stage['lanes']), 'length' => $stage['length']]) }}</span></p>
                        <span class="lnc-who">@foreach ($stage['lanes'] as $lane)<i class="lnc-bubble lnc-v{{ $lane['color'] }}">{{ mb_substr($lane['name'], 0, 1) }}</i>@endforeach</span>
                        <span class="lnc-sharebtns"><span><x-site.icon name="link" size="16" />{{ __('next_chatgpt.steps.share.link') }}</span><span><x-site.icon name="video" size="16" />{{ __('next_chatgpt.steps.share.video') }}</span></span>
                    </div>
                    <p class="lnc-sr">{{ __('next_chatgpt.stage.alt', ['song' => $stage['song']['title'], 'words' => \App\Support\Pages\Landing::sentence(array_merge(...$stage['lines']))]) }}</p>
                @endif
            </div>

            <section class="lnc-cap lnc-cap-0" data-lnc-scene="0" aria-labelledby="lnc-h">
                <h1 id="lnc-h">{{ __('next_chatgpt.headline') }} <em>{{ __('next_chatgpt.headline_em') }}</em></h1>
                <div class="lnc-cap-side">
                    <p class="lnc-lead">{{ __('next_chatgpt.lead') }}</p>
                    <a class="lnc-btn" href="{{ $links['start'] }}">{{ __('next_chatgpt.start') }} <x-site.icon name="arrow" size="18" /></a>
                </div>
                @if ($stage !== null)
                    <p class="lnc-hint">{{ __('next_chatgpt.stage.hint') }}</p>
                    <p class="lnc-credit">{{ $stage['credit'] }}</p>
                @endif
                <p class="lnc-cue" aria-hidden="true">{{ __('next_chatgpt.cue') }}</p>
            </section>

            @foreach ($steps as $i => $step)
                <section class="lnc-cap lnc-cap-step" id="lnc-{{ $step }}" data-lnc-scene="{{ $i + 1 }}" aria-labelledby="lnc-{{ $step }}-h">
                    <div class="lnc-cap-in">
                        <p class="lnc-kick"><b>{{ __('next_chatgpt.step_of', ['n' => $i + 1, 'of' => count($steps)]) }}</b> {{ __('next_chatgpt.steps.'.$step.'.kick') }}</p>
                        <h2 id="lnc-{{ $step }}-h">{{ __('next_chatgpt.steps.'.$step.'.title') }} <em>{{ __('next_chatgpt.steps.'.$step.'.em') }}</em></h2>
                        <p class="lnc-body">{{ __('next_chatgpt.steps.'.$step.'.body') }}</p>
                        @if ($step === 'hear' && $stage !== null)
                            @php($focus = $stage['learn'][\App\Support\Pages\LandingNextChatgpt::FOCUS] ?? collect($stage['learn'])->first())
                            <p class="lnc-row">
                                <button type="button" class="lnc-btn lnc-btn-part" data-lnc-try hidden data-label="{{ __('next_chatgpt.stage.hear_only', ['part' => ':part']) }}" data-label-back="{{ __('next_chatgpt.stage.hear_all') }}">{{ __('next_chatgpt.stage.hear_only', ['part' => $focus['name']]) }}</button>
                                <a class="lnc-btn lnc-btn-quiet" href="{{ $focus['href'] }}" data-lnc-learn-link data-label="{{ __('next_chatgpt.stage.learn', ['part' => ':part']) }}"><span data-lnc-learn-label>{{ __('next_chatgpt.stage.learn', ['part' => $focus['name']]) }}</span> <x-site.icon name="arrow" size="16" /></a>
                                <span class="lnc-note">{{ __('next_chatgpt.stage.learn_note') }}</span>
                            </p>
                        @elseif ($step === 'along')
                            <p class="lnc-seg" aria-hidden="true"><span>{{ __('next_chatgpt.steps.along.listen') }}</span><span class="lnc-on">{{ __('next_chatgpt.steps.along.along') }}</span><span>{{ __('next_chatgpt.steps.along.alone') }}</span></p>
                        @elseif ($step === 'record')
                            <p class="lnc-row" aria-hidden="true"><span class="lnc-recdot"><b></b>{{ __('next_chatgpt.steps.record.recording') }}</span></p>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- The courses: a course as a rising path of lesson bubbles, and the site's three counts, read live. --}}
        <section class="lnc-scene lnc-learn" id="lnc-courses" data-lnc-scene="5" aria-labelledby="lnc-courses-h">
            <div class="lnc-sky" aria-hidden="true"></div>
            <div class="lnc-scene-in">
                <p class="lnc-kick">{{ __('next_chatgpt.courses.kick') }}</p>
                <h2 id="lnc-courses-h">{{ __('next_chatgpt.courses.title') }} <em>{{ __('next_chatgpt.courses.em') }}</em></h2>
                <p class="lnc-body">{{ __('next_chatgpt.courses.body') }}</p>
                <p class="lnc-row">
                    <a class="lnc-btn lnc-btn-solid" href="{{ $links['start'] }}">{{ __('next_chatgpt.courses.start') }} <x-site.icon name="arrow" size="18" /></a>
                    <a class="lnc-btn" href="{{ $links['courses'] }}">{{ __('next_chatgpt.courses.all', ['count' => $counts['courses']]) }}</a>
                </p>
            </div>
            <ol class="lnc-path" role="img" aria-label="{{ __('next_chatgpt.courses.path') }}">
                @foreach (range(1, 7) as $n)
                    <li class="lnc-v{{ ($n - 1) % 4 + 1 }}" style="--i: {{ $n - 1 }}"><i class="lnc-bubble" aria-hidden="true">{{ $n }}</i></li>
                @endforeach
            </ol>
            <ul class="lnc-facts">
                <li><a href="{{ $links['courses'] }}"><b>{{ $counts['courses'] }}</b> {{ __('next_chatgpt.courses.facts.courses') }}</a></li>
                <li><a href="{{ $links['songs'] }}"><b>{{ $counts['songs'] }}</b> {{ __('next_chatgpt.courses.facts.songs') }}</a></li>
                <li><a href="{{ $links['tools'] }}"><b>{{ $counts['tools'] }}</b> {{ __('next_chatgpt.courses.facts.tools') }}</a></li>
            </ul>
        </section>

        {{-- For choirs: the four lanes as a choir group's four parts, in the app's own words for each status
             (AppWords); the names are samples and the caption says so. Initials only, never a drawn person. --}}
        @php($groupParts = ['soprano' => 'groups.status.recorded', 'alto' => 'groups.status.recorded', 'tenor' => 'groups.status.started', 'bass' => 'groups.status.none'])
        @php($sampleNames = (array) \Illuminate\Support\Facades\Lang::get('site.teams.group_sample'))
        <section class="lnc-scene lnc-choir" id="lnc-choirs" data-lnc-scene="6" aria-labelledby="lnc-choirs-h">
            <div class="lnc-sky" aria-hidden="true"></div>
            <div class="lnc-scene-in">
                <p class="lnc-kick">{{ __('next_chatgpt.choir.kick') }}</p>
                <h2 id="lnc-choirs-h">{{ __('next_chatgpt.choir.title') }} <em>{{ __('next_chatgpt.choir.em') }}</em></h2>
                <p class="lnc-body">{{ __('next_chatgpt.choir.body') }}</p>
                <p class="lnc-row"><a class="lnc-btn lnc-btn-solid" href="{{ $links['choirs'] }}">{{ __('next_chatgpt.choir.action') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
            <figure class="lnc-board">
                <ul>
                    @foreach ($groupParts as $part => $statusWords)
                        @php($name = (string) ($sampleNames[$loop->index] ?? ''))
                        <li class="lnc-v{{ $loop->iteration }}"><b>{{ __('pages.landing.hero.parts.'.$part) }}</b><i class="lnc-bubble" aria-hidden="true">{{ collect(explode(' ', $name))->map(fn (string $word): string => mb_substr($word, 0, 1))->implode('') }}</i><span class="lnc-who-name">{{ $name }}</span><span class="lnc-st">{{ \App\Support\Site\AppWords::get($statusWords) }}</span></li>
                    @endforeach
                </ul>
                <figcaption>{{ __('site.teams.group_caption') }}</figcaption>
            </figure>
        </section>

        {{-- The close: the four bars (the mark), the one line, Start singing, and the ways on. --}}
        <section class="lnc-scene lnc-close" id="lnc-close" data-lnc-scene="7" aria-labelledby="lnc-close-h">
            <div class="lnc-sky" aria-hidden="true"></div>
            <span class="lnc-bars" aria-hidden="true">@foreach ([100, 74, 88, 62] as $w)<b class="lnc-v{{ $loop->iteration }}" style="--w: {{ $w }}%"></b>@endforeach</span>
            <h2 id="lnc-close-h">{{ __('next_chatgpt.close.title') }} <em>{{ __('next_chatgpt.close.em') }}</em></h2>
            <a class="lnc-cta" href="{{ $links['start'] }}">{{ __('next_chatgpt.start') }} <x-site.icon name="arrow" size="20" /></a>
            <p class="lnc-body">{{ __('next_chatgpt.close.note') }}</p>
            <p class="lnc-plans">{{ __('next_chatgpt.close.plans') }} <a href="{{ $links['pricing'] }}">{{ __('next_chatgpt.close.plans_link') }} <x-site.icon name="arrow" size="14" /></a></p>
            <nav class="lnc-ways" aria-label="{{ __('next_chatgpt.close.ways') }}">
                @foreach (['songs', 'courses', 'tools', 'pricing'] as $page)
                    <a href="{{ $links[$page] }}">{{ __('next_chatgpt.nav.'.$page) }}</a>
                @endforeach
                <a href="{{ $links['help'] }}">{{ __('next_chatgpt.close.help') }}</a>
                <a href="{{ $links['privacy'] }}">{{ __('next_chatgpt.close.privacy') }}</a>
                <a href="{{ $links['terms'] }}">{{ __('next_chatgpt.close.terms') }}</a>
            </nav>
        </section>
    </main>

    {{-- Where the page is: one dot a scene, each a link to it. --}}
    <nav class="lnc-rail" aria-label="{{ __('next_chatgpt.nav.scenes') }}">
        <a href="#lnc-stage" data-lnc-dot="0" aria-label="{{ __('next_chatgpt.headline') }} {{ __('next_chatgpt.headline_em') }}"></a>
        @foreach ($steps as $i => $step)
            <a href="#lnc-{{ $step }}" data-lnc-dot="{{ $i + 1 }}" aria-label="{{ __('next_chatgpt.steps.'.$step.'.kick') }}"></a>
        @endforeach
        <a href="#lnc-courses" data-lnc-dot="5" aria-label="{{ __('next_chatgpt.courses.kick') }}"></a>
        <a href="#lnc-choirs" data-lnc-dot="6" aria-label="{{ __('next_chatgpt.choir.kick') }}"></a>
        <a href="#lnc-close" data-lnc-dot="7" aria-label="{{ __('next_chatgpt.start') }}"></a>
    </nav>

    {{-- The play control's words while it loads (js/public/play-state.js), as on every public page. --}}
    <template id="s-play-words" data-loading="{{ __('pages.site.play_loading') }}" data-slow="{{ __('pages.site.play_slow') }}" data-failed="{{ __('pages.site.play_failed') }}"></template>
    {{-- This page load's one anonymous count, under the page's own kind (LandingNextChatgpt::KIND); the script sends it. --}}
    @if ($visit !== null)
        <script type="application/json" data-lnc-visit>@json(['kind' => $visit])</script>
    @endif
    @if ($stage !== null)
        <script type="module" src="/js/public/mixer.js"></script>
    @endif
    <script type="module" src="/js/public/landing-next-chatgpt.js"></script>
</body>
</html>
