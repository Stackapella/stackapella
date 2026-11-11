{{-- One voice type (/voice-types/<type>) in Hillel's design (2026-11-11, "Alto Voice page redesign"): the heading, lead,
     its facts and the ways on at left with frontend's keyboard to hear the range at right; the guide in one reading
     column with its contents beside it; every type's range on one scale; the song parts inside its range, each a bar
     inside it; then About Stackapella, the questions and the free tools. A phone stacks it in the same order, without the
     contents. The numbers are Reference::VOICE_TYPES's and the song files' own (App\Support\Pages\VoiceTypePage). The
     intervals and chords keep site/reference.blade.php. Words: lang/en/site.php `reference.voices`. --}}
@extends('public.layout')

{{-- The arcade screen in the game skin (<html data-gs>): the reference pages take the tools' full game look. --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
    @include('site.tools._preloads', ['preloads' => $preloads])
    <x-site.json-ld :data="$ld" />
@endsection

@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))

@section('content')
    @include('site.tools._brand-row', ['onTool' => true, 'toolName' => (string) $index['eyebrow'], 'toolSlug' => null, 'ref' => $kind, 'trail' => [[(string) $index['eyebrow'], '/'.$kind], [(string) $page['name']]]])
    <main>
        <section class="s-wrap s-vt-hero" aria-labelledby="page-h">
            <div class="s-vt-intro">
                <p class="s-teyebrow">{{ str_replace([':n', ':total'], [$vt['n'], $vt['total']], $index['count']) }}</p>
                <h1 id="page-h">{{ $page['headline'] }}</h1>
                <p class="s-vt-lead">{{ $page['lead'] }}</p>
                <dl class="s-spart-facts s-vt-facts">
                    <div><dt>{{ $card['rangeLabel'] }}</dt><dd translate="no">{{ $page['range'] }}</dd></div>
                    <div><dt>{{ $card['partLabel'] }}</dt><dd>{{ $card['part'][$choir] ?? '' }}</dd></div>
                    @if ($vt['neighbours'] !== [])
                        <div><dt>{{ $index['neighbours'] }}</dt><dd class="s-vt-near">@foreach ($vt['neighbours'] as $near)<a href="{{ $near['url'] }}" class="s-lk">{{ $near['name'] }}</a>@endforeach</dd></div>
                    @endif
                </dl>
                <p class="s-vt-btns">
                    <a href="{{ $data['rangeTest'] }}" class="btn-primary">{{ $card['find'] }} <x-site.icon name="arrow" size="16" /></a>
                    @if ($vt['songs'] !== [])
                        <a href="#parts" class="btn-secondary">{{ str_replace(':name', $page['name'], $index['to_songs']) }}</a>
                    @endif
                </p>
            </div>

            {{-- Frontend's card, bare (the keyboard and its three plays): the facts and the way to the range test are at left. --}}
            <section class="s-vt-hear" aria-labelledby="hear-h">
                <div class="s-vt-hear-top"><h2 id="hear-h">{{ $index['hear'] }}</h2><span translate="no">{{ $page['range'] }}</span></div>
                {{-- Its room, held until page.js has mounted it (data-state), so nothing jumps: the bare card is 188 px when
                     its plays fit one row and 224 px on a phone, where they take two; held a little under the shorter so it
                     never overshoots (tests/flows/site_reference.py checks). --}}
                <div class="s-ref [&:not([data-state])]:min-h-[184px]" data-tool="ref-{{ $kind }}" data-src="{{ $module }}" data-mount="{{ $mount }}">
                    <script type="application/json" data-tool-words>@json($card)</script>
                    <script type="application/json" data-tool-data>@json($data)</script>
                </div>
                <p class="s-tool-say" role="status" data-tool-say hidden></p>
            </section>
        </section>

        <div class="s-vt-read">
            <div class="s-wrap s-vt-body">
                <nav class="s-vt-toc" aria-labelledby="toc-h">
                    <p id="toc-h" class="s-kick">{{ $index['toc'] }}</p>
                    <ul>
                        @foreach ($vt['contents'] as [$href, $label])
                            <li><a href="{{ $href }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </nav>
                <div class="s-vt-prose">
                    @foreach ((array) ($page['guide'] ?? []) as $section)
                        <section class="s-tool-guide" aria-labelledby="guide-{{ $loop->iteration }}">
                            <h2 id="guide-{{ $loop->iteration }}">{{ $section['heading'] }}</h2>
                            @foreach ((array) $section['body'] as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </section>
                    @endforeach

                    {{-- Its lessons to go on with (marketing's map), two across from 640 px. --}}
                    @include('site.courses._lessons-across', ['path' => '/'.request()->path(), 'nested' => true, 'stacked' => true])
                </div>
            </div>
        </div>

        {{-- Every voice type's usual range on one scale, this one lit, each a link to its page. --}}
        <section id="types" class="s-wrap s-sec2 s-vt-where" aria-labelledby="types-h">
            <p class="s-kick">{{ $index['where_kick'] }}</p>
            <h2 id="types-h">{{ str_replace(':name', $vt['lower'], $index['where']) }}</h2>
            <div class="s-vt-rung s-vt-scale" aria-hidden="true">
                <span></span>
                <span class="s-vt-ticks">@foreach ($vt['ticks'] as $tick)<span style="--l: {{ $tick['left'] }}%" translate="no">{{ $tick['name'] }}</span>@endforeach</span>
            </div>
            <ol class="s-vt-ladder" aria-label="{{ str_replace([':low', ':high'], [$vt['ticks'][0]['name'], $vt['ticks'][count($vt['ticks']) - 1]['name']], $index['where_label']) }}">
                @foreach ($vt['ladder'] as $rung)
                    <li>
                        <a href="{{ $rung['url'] }}" @class(['s-vt-rung', 's-v'.$rung['colour'], 's-vt-here' => $rung['current']]) @if ($rung['current']) aria-current="page" @endif>
                            <span><b>{{ $rung['name'] }}</b><span translate="no">{{ $rung['range'] }}</span></span>
                            <span class="s-vt-track" aria-hidden="true"><span class="s-vt-bar" style="--l: {{ $rung['left'] }}%; --w: {{ $rung['width'] }}%"></span></span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- The song parts inside its range, easiest first, each a bar inside the range. --}}
        @if ($vt['songs'] !== [])
            <section id="parts" class="s-wrap s-sec2 s-vt-parts s-v{{ $vt['colour'] }}" aria-labelledby="parts-h">
                <div>
                    <p class="s-kick">{{ $index['parts_kick'] }}</p>
                    <h2 id="parts-h">{{ $vt['partsHeading'] }}</h2>
                    <p>{{ str_replace(':name', $vt['lower'], $index['parts_lead']) }}</p>
                </div>
                <div>
                    <ul class="s-vt-songs">
                        @foreach ($vt['songs'] as $song)
                            <li>
                                <a href="{{ $song['url'] }}">
                                    <span><b>{{ $song['title'] }}</b><span>{{ $song['name'] }}</span></span>
                                    <span class="s-vt-songrange" translate="no">{{ $song['range'] }}</span>
                                    <span class="s-vt-track" aria-hidden="true"><span class="s-vt-bar" style="--l: {{ $song['left'] }}%; --w: {{ $song['width'] }}%"></span></span>
                                    <x-site.icon name="arrow" size="16" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <p class="s-vt-note" translate="no">{{ str_replace(':range', $page['range'], $index['parts_bars']) }}</p>
                </div>
            </section>
        @endif

        @include('site.tools._about', ['tie' => $ix['tie'], 'tryLink' => $ix['try_link']])

        @if (! empty($page['faq']))
            <section id="faq" class="s-wrap s-sec s-vt-faq" aria-labelledby="faq-h">
                <div>
                    <p class="s-kick">{{ $index['faq_kick'] }}</p>
                    <h2 id="faq-h">{{ str_replace(':plural', $index['plural'][$slug] ?? $vt['lower'], $index['faq_h']) }}</h2>
                </div>
                <div class="s-faq s-faq-round">
                    @foreach ((array) $page['faq'] as [$question, $answer])
                        <details>
                            <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                            <p>{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if (\App\Support\Site\SitePages::hubLive())
            @include('site.tools._directory')
        @endif
    </main>
    <x-site.footer />
    <script type="module" src="/js/public/tools/page.js"></script>
@endsection
