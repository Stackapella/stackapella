{{-- One free tool (/tools/<tool>, docs/tools-plan.md): its name as the H1, the tool itself above the fold
     (js/public/tools/page.js mounts js/public/tools/<tool>.js on the stage), how to use it in two lines,
     its one way into the app (?ref=tool-<tool>; "Open the app" instead for a visitor who's signed in),
     a short FAQ and the other tools. Words: lang/en/site.php `tools`. --}}
@extends('public.layout')

{{-- The arcade screen in the game skin (<html data-gs>): the tools take game mode's full look (the game agent's). --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
    {{-- Each tool installs as itself ("Tuner"), and works offline once visited (/tools-sw.js, registered by page.js). --}}
    <link rel="manifest" href="/tools/{{ $page['slug'] }}/manifest.webmanifest">
    @include('site.tools._preloads', ['preloads' => (array) ($page['preloads'] ?? [])])
    <x-site.json-ld :data="\App\Support\Site\StructuredData::tool((string) $page['name'], $meta, array_values(array_filter((array) ($page['faq'] ?? []), 'is_array')))" />
@endsection

@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))
@php($mic = (array) \Illuminate\Support\Facades\Lang::get('pages.site.note_demo'))
@php($cardWords = (array) ($page['card'] ?? []))

@section('content')
    {{-- The brand row (docs/tools-plan.md, "Tool page frame"; Hillel: "our logo/branding at the top"): the same in the
         browser and installed, padded for the phone's top inset. The mark and wordmark link to /tools; the Theme button
         opens the look's choices; on a wide screen the Game mode pill and a quiet Open the app join it. --}}
    @include('site.tools._brand-row', ['onTool' => true, 'toolName' => (string) $page['name'], 'toolSlug' => (string) $page['slug'], 'trail' => \App\Support\Site\SitePages::hubLive() ? [[__('pages.nav.tools'), '/tools'], [(string) $page['name']]] : null])
    <main>

        <section class="s-wrap" aria-label="{{ $page['name'] }}">
            <div class="s-tool" data-tool="{{ $page['slug'] }}" data-src="{{ $page['module'] }}" data-mount="{{ $page['mount'] }}" data-denied="{{ $mic['why_denied'] }}" data-no-mic="{{ $mic['why_no_mic'] }}" data-unsupported="{{ $mic['why_unsupported'] }}">
                <script type="application/json" data-tool-words>@json($cardWords)</script>
                {{-- What the tool takes besides its words (the key finder's songs): mount options, so it needs no request. --}}
                @if ($data !== [])
                    <script type="application/json" data-tool-data>@json($data)</script>
                @endif
                @includeIf('site.tools._'.$page['slug'])
            </div>
            <p class="s-tool-say" role="status" data-tool-say hidden></p>
            {{-- The free lesson it leads to, shown in the tool's bar once it has a result (page.js): never before. Outside the
                 tool's box, which the tool's mount fills afresh. --}}
            @if ($lesson !== null)
                <script type="application/json" data-tool-lesson>@json($lesson)</script>
            @endif
        </section>

        {{-- The page's heading comes after the tool (the shared tool layout has none: docs/tools-plan.md, "Tool page
             frame"), with the searched words, the lede and how to use it (the trail is at the top, in the tool bar). --}}
        <section class="s-wrap s-sec2 s-tool-more" aria-labelledby="page-h">
            <p class="s-teyebrow">{{ $page['eyebrow'] ?? $ix['eyebrow'] }}</p>
            <h1 id="page-h">{{ $page['headline'] }}</h1>
            <p class="s-lede s-tool-lede">{{ $page['lead'] }}</p>
            <h2 class="s-kick">{{ $ix['how'] }}</h2>
            <ol class="s-tool-how">
                @foreach ((array) $page['how'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ol>
            @if ($also !== [])
                <p class="s-tool-related s-tool-also">{{ $ix['also'] }} @foreach ($also as $other)<a href="/tools/{{ $other['slug'] }}" class="s-lk">{{ $other['name'] }}</a>@if (! $loop->last)<span aria-hidden="true"> · </span>@endif @endforeach</p>
            @endif
        </section>

        {{-- A whole reference table rendered in the page (the note chart's C0 to B8 at A4 = 440), for search and for a
             reader without the script: its words are the page's `chart`, its rows the server's. --}}
        @if ($chart !== null && is_array($page['chart'] ?? null))
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="chart-h">
                <h2 id="chart-h">{{ $page['chart']['heading'] }}</h2>
                @foreach ((array) ($page['chart']['body'] ?? []) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                <div class="s-tool-table"><table>
                    <caption>{{ $page['chart']['caption'] }}</caption>
                    <thead><tr><th scope="col">{{ $page['chart']['note'] }}</th><th scope="col">{{ $page['chart']['hz'] }}</th><th scope="col">{{ $page['chart']['midi'] }}</th></tr></thead>
                    <tbody>@foreach ($chart as $row)<tr><th scope="row">{{ $row['name'] }}</th><td>{{ $row['hz'] }}</td><td>{{ $row['midi'] }}</td></tr>@endforeach</tbody>
                </table></div>
            </section>
        @endif

        {{-- What's worth knowing around the tool (cents, tuning a guitar, the voice types): a few useful sections, never filler. --}}
        @foreach ((array) ($page['guide'] ?? []) as $section)
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="guide-{{ $loop->iteration }}">
                <h2 id="guide-{{ $loop->iteration }}">{{ $section['heading'] }}</h2>
                @foreach ((array) $section['body'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @if (! empty($section['table']))
                    <div class="s-tool-table"><table>
                        <thead><tr>@foreach ((array) $section['table']['head'] as $cell)<th scope="col">{{ $cell }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ((array) $section['table']['rows'] as $row)<tr>@foreach ((array) $row as $cell)@if ($loop->first)<th scope="row">@if (isset($section['table']['links'][$loop->parent->index]))<a href="{{ $section['table']['links'][$loop->parent->index] }}" class="s-lk">{{ $cell }}</a>@else{{ $cell }}@endif</th>@else<td>{{ $cell }}</td>@endif @endforeach</tr>@endforeach</tbody>
                    </table></div>
                @endif
            </section>
        @endforeach
        {{-- The reference pages about the same things (/intervals, /chords, /voice-types): one quiet line after the guide,
             whose table can also link each row to its own page (the table's `links`). --}}
        @if (is_array($page['reference'] ?? null))
            <p class="s-wrap s-tool-related s-tool-ref"><a href="{{ $page['reference'][1] }}" class="s-lk">{{ $page['reference'][0] }}</a></p>
        @endif

        {{-- About Stackapella: the page's one accent block, after the tool has proved itself and its guide has answered
             the search (docs/tools/below-the-fold.md). A tool that reports a result (the range test's voice type) can
             turn its headline and button into ones for that result. --}}
        @if ($result !== null)
            <script type="application/json" data-tool-result>@json($result)</script>
        @endif
        @include('site.tools._about', ['tie' => $page['tie'], 'tryLink' => $page['try_link']])

        @if (! empty($page['faq']))
            <section class="s-wrap s-sec" aria-labelledby="faq-h">
                <h2 id="faq-h" class="s-kick">{{ $ix['faq'] }}</h2>
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

        {{-- More free tools: the next tool this one points to, then every other live tool by group (once the hub is live). --}}
        @if (\App\Support\Site\SitePages::hubLive())
            @include('site.tools._directory')
        @endif
    </main>
    <x-site.footer />
    <script type="module" src="/js/public/tools/page.js"></script>
@endsection
