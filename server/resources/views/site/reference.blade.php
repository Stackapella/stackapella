{{-- One reference page (/intervals/<interval>, /chords/<chord>, /voice-types/<type>; docs/seo-growth-plan.md item 4): the
     searched heading first, then frontend's card to hear it and see it (js/public/tools/page.js mounts
     js/public/reference-cards.js on the stage with the page's words and data, so it needs no request), a short guide,
     the family's other pages, About Stackapella, a short FAQ and the free tools. Words: lang/en/site.php `reference`. --}}
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
        {{-- The top (Hillel's width design, 2026-11-11: "apply these changes to all pages which will benefit"): the eyebrow and
             heading at left, the lead large at right from 900 px. Then two columns: frontend's card at left with its facts
             under it, and beside it the guide and the lessons to go on with, so nothing sits under the card with the right
             side empty. A phone stacks it all in the same order. --}}
        <section class="s-wrap s-thead s-ref-head" aria-labelledby="page-h">
            <div>
                <p class="s-teyebrow">{{ $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['headline'] }}</h1>
            </div>
            <p class="s-ref-lead">{{ $page['lead'] }}</p>
        </section>

        <div class="s-wrap s-ref-body">
            <section class="s-ref-cardcol" aria-label="{{ $page['name'] }}">
                {{-- Its card's room, held until page.js has mounted it (data-state), so the guide below doesn't jump: each family's
                     shortest card at that width, measured on every page (the octave, the augmented chord, the alto), a little
                     under so it never overshoots (tests/flows/site_reference.py checks): a phone's, then from 768 px, where the
                     card is 590 to 700 px wide in its column or alone. --}}
                @php($room = ['intervals' => '[&:not([data-state])]:min-h-[540px] md:[&:not([data-state])]:min-h-[495px]', 'chords' => '[&:not([data-state])]:min-h-[590px] md:[&:not([data-state])]:min-h-[560px]', 'voice-types' => '[&:not([data-state])]:min-h-[560px] md:[&:not([data-state])]:min-h-[535px]'][$kind] ?? '')
                <div class="s-ref {{ $room }}" data-tool="ref-{{ $kind }}" data-src="{{ $module }}" data-mount="{{ $mount }}">
                    <script type="application/json" data-tool-words>@json($card)</script>
                    <script type="application/json" data-tool-data>@json($data)</script>
                </div>
                <p class="s-tool-say" role="status" data-tool-say hidden></p>
            </section>

            <div class="s-ref-prose">
                @foreach ((array) ($page['guide'] ?? []) as $section)
                    <section class="s-tool-guide" aria-labelledby="guide-{{ $loop->iteration }}">
                        <h2 id="guide-{{ $loop->iteration }}">{{ $section['heading'] }}</h2>
                        @foreach ((array) $section['body'] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                        @if (! empty($section['table']))
                            <div class="s-tool-table"><table>
                                <thead><tr>@foreach ((array) $section['table']['head'] as $cell)<th scope="col">{{ $cell }}</th>@endforeach</tr></thead>
                                <tbody>@foreach ((array) $section['table']['rows'] as $row)<tr>@foreach ((array) $row as $cell)@if ($loop->first)<th scope="row">{{ $cell }}</th>@else<td>{{ $cell }}</td>@endif @endforeach</tr>@endforeach</tbody>
                            </table></div>
                        @endif
                    </section>
                @endforeach

                {{-- Its lessons to go on with (marketing's map), at the end of the teaching, one card a row. --}}
                @include('site.courses._lessons-across', ['path' => '/'.request()->path(), 'nested' => true, 'stacked' => true])
            </div>
        </div>

        {{-- A voice type's song parts that sit inside its range, each with its own page. --}}
        @if ($songs !== [])
            <section class="s-wrap s-sec2" aria-labelledby="songs-h">
                <h2 id="songs-h" class="s-kick">{{ $index['fits'] }}</h2>
                <p class="s-tool-guide">{{ $index['fits_lead'] }}</p>
                <ul class="s-cards s-tool-cards">
                    @foreach ($songs as $song)
                        <li>
                            <a href="{{ $song['url'] }}" class="s-card">
                                <b>{{ str_replace([':song', ':part'], [$song['title'], $song['name']], $index['fits_part']) }}</b>
                                <span translate="no">{{ str_replace([':low', ':high'], [$song['lowName'], $song['highName']], (string) __('site.reference.voices.card.range')) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- The family's other pages, one tap from each: four across from 1024 px, each with its number (an interval's
             semitones, else its place) and the line under its name (Reference::rows). --}}
        @if ($others !== [])
            <section class="s-wrap s-sec2" aria-labelledby="others-h">
                <h2 id="others-h" class="s-kick">{{ $index['others'] }}</h2>
                <ul class="s-ref-family s-tool-cards">
                    @foreach ($others as $other)
                        <li>
                            <a href="{{ $other['url'] }}" class="s-card s-ref-fam">
                                <span class="s-ref-fam-n" aria-hidden="true">{{ $other['n'] }}</span>
                                <span><b>{{ $other['name'] }}</b>@if ($other['line'] !== null)<span translate="no">{{ $other['line'] }}</span>@endif</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('site.tools._about', ['tie' => $ix['tie'], 'tryLink' => $ix['try_link']])

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

        @if (\App\Support\Site\SitePages::hubLive())
            @include('site.tools._directory')
        @endif
    </main>
    <x-site.footer />
    <script type="module" src="/js/public/tools/page.js"></script>
@endsection
