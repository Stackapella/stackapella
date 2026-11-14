{{-- The free tools' hub (/tools, docs/tools-plan.md; Hillel's tools page, 2026-11-12): the count, a search and the groups
     as chips, Most used as three big cards with a small drawing each, then every tool in its group, a Mic badge where it
     listens, the warm-ups, and the About panel with its real app pictures. The chips are links to their sections and the
     search narrows the cards as you type (js/public/tools/hub.js); without the script every tool is listed and linked.
     Words: lang/en/site.php `tools.index`. --}}
@extends('public.layout')

{{-- The arcade screen in the game skin (<html data-gs>): the tools take game mode's full look (the game agent's). --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
    <x-site.json-ld :data="\App\Support\Site\StructuredData::tools((string) $words['eyebrow'], $meta['url'], $tools)" />
@endsection

@section('content')
    @include('site.tools._brand-row')
    <main>
        <section class="s-wrap s-thead s-thead-hub s-th-hero" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ str_replace(':count', (string) count($tools), $words['eyebrow_count']) }}</p>
            <h1 id="page-h">{{ $words['headline'] }}</h1>
            <p class="s-lede">{{ $words['lead'] }}</p>
            <label class="s-sh-search s-th-search">
                <x-site.icon name="search" size="18" />
                <input type="search" placeholder="{{ $words['search'] }}" aria-label="{{ $words['search_label'] }}" autocomplete="off" data-tools-search>
            </label>
            <nav class="s-th-chips" aria-label="{{ $words['chips_label'] }}">
                <a href="#tools-all" class="s-sh-filter is-on" aria-current="true" data-tools-filter="">{{ $words['all'] }}<span>{{ count($tools) }}</span></a>
                @foreach ($groups as $group)
                    <a href="#tools-{{ $group['key'] }}" class="s-sh-filter s-v{{ $group['colour'] }}" data-tools-filter="{{ $group['key'] }}"><i aria-hidden="true"></i>{{ $words['groups'][$group['key']] }}<span>{{ count($group['tools']) }}</span></a>
                @endforeach
            </nav>
        </section>

        @if ($most !== [])
            <section class="s-wrap s-th-most-wrap" aria-labelledby="most-h" data-tools-most>
                <h2 id="most-h" class="s-th-label">{{ $words['most_used'] }}</h2>
                <ul class="s-th-most">
                    @foreach ($most as $tool)
                        <li>
                            <a href="{{ $tool['url'] }}" class="s-th-big">
                                {{-- A small drawing of the tool at work (static: never the mic). --}}
                                <span class="s-th-pic" aria-hidden="true">
                                    @if ($tool['slug'] === 'tuner')
                                        <span class="s-th-pic-read"><b>{{ $words['preview']['note'] }}</b><em class="s-th-good">{{ $words['preview']['cents'] }}</em></span>
                                        <span class="s-th-ticks">@foreach (range(0, 10) as $i)<i @class(['is-on' => $i === 5])></i>@endforeach</span>
                                    @elseif ($tool['slug'] === 'metronome')
                                        <span class="s-th-pic-read"><b>{{ $words['preview']['bpm'] }}</b><em>{{ $words['preview']['beat'] }}</em></span>
                                        <span class="s-th-beats">@foreach (range(0, 3) as $i)<i @class(['is-on' => $i === 0])></i>@endforeach</span>
                                    @elseif ($tool['slug'] === 'vocal-range-test')
                                        <span class="s-th-pic-read"><b>{{ $words['preview']['range'] }}</b><em class="s-th-part">{{ $words['preview']['part'] }}</em></span>
                                        <span class="s-th-keys">@foreach (range(0, 15) as $i)<i @class(['is-on' => $i >= 4 && $i <= 10])></i>@endforeach</span>
                                    @else
                                        <span class="s-who-ic"><x-site.icon :name="$tool['icon']" size="22" /></span>
                                    @endif
                                </span>
                                <span class="s-th-big-head"><b>{{ $tool['name'] }}</b><span class="s-th-open">{{ $words['open'] }}&#8288;<x-site.icon name="arrow" size="14" /></span></span>
                                <span class="s-th-line">{{ $tool['summary'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="s-wrap s-th-groups" id="tools-all" aria-label="{{ $words['directory'] }}">
            @foreach ($groups as $group)
                <section class="s-th-grp s-v{{ $group['colour'] }}" id="tools-{{ $group['key'] }}" aria-labelledby="grp-{{ $group['key'] }}" data-tools-group="{{ $group['key'] }}">
                    <div class="s-th-grp-head">
                        <h2 id="grp-{{ $group['key'] }}"><i aria-hidden="true"></i>{{ $words['groups'][$group['key']] }}</h2>
                        <p>{{ $words['group_lines'][$group['key']] }}</p>
                    </div>
                    <ul class="s-th-list">
                        @foreach ($group['tools'] as $tool)
                            <li data-tools-card data-search="{{ mb_strtolower($tool['name'].' '.$tool['summary'].' '.$tool['tag'].' '.$words['groups'][$group['key']]) }}">
                                <a href="{{ $tool['url'] }}" class="s-th-card">
                                    <span class="s-th-card-top"><b>{{ $tool['name'] }}</b>@if ($tool['mic'])<span class="s-th-mic" title="{{ $words['mic_label'] }}"><span aria-hidden="true">{{ $words['mic'] }}</span><span class="sr-only">{{ $words['mic_label'] }}</span></span>@endif<x-site.icon name="arrow" size="16" /></span>
                                    <span class="s-th-line">{{ $tool['summary'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
            <p class="s-th-none" role="status" hidden data-tools-none data-text="{{ $words['no_match'] }}"><span data-tools-none-text></span> <button type="button" class="s-lk" data-tools-reset>{{ $words['show_all'] }}</button></p>
            <p class="s-th-warmups">{{ $words['warming_up'] }} <a href="/warmups" class="s-lk">{{ $words['warmups'] }}&#8288;<x-site.icon name="arrow" size="16" /></a></p>
            {{-- The embeds for choir, school and teacher sites (the internal-link audit, 2026-11-13: nothing linked /tools/embed). --}}
            <p class="s-th-warmups">{{ $words['embed_line'] }} <a href="/tools/embed" class="s-lk">{{ $words['embed_link'] }}&#8288;<x-site.icon name="arrow" size="16" /></a></p>
        </section>
        @include('site.tools._about', ['tie' => $words['tie'], 'tryLink' => $words['try_link']])
    </main>
    <x-site.footer />
    {{-- The tools' own service worker (no tool on this page: page.js only registers it), and the hub's search and chips. --}}
    <script type="module" src="/js/public/tools/page.js"></script>
    <script type="module" src="/js/public/tools/hub.js"></script>
@endsection
