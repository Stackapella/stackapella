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
        <section class="s-wrap s-thead s-thead-hub" aria-labelledby="page-h">
            <p class="s-teyebrow">{{ $page['eyebrow'] }}</p>
            <h1 id="page-h">{{ $page['headline'] }}</h1>
            <p class="s-lede">{{ $page['lead'] }}</p>
        </section>

        <section class="s-wrap" aria-label="{{ $page['name'] }}">
            {{-- Its card's room, held until page.js has mounted it (data-state), so the guide below doesn't jump: each family's
                 shortest card, measured on a phone and a desktop (tests/flows/site_reference.py checks it never overshoots). --}}
            @php($room = ['intervals' => '[&:not([data-state])]:min-h-[580px] md:[&:not([data-state])]:min-h-[484px]', 'chords' => '[&:not([data-state])]:min-h-[648px] md:[&:not([data-state])]:min-h-[550px]', 'voice-types' => '[&:not([data-state])]:min-h-[596px] md:[&:not([data-state])]:min-h-[524px]'][$kind] ?? '')
            <div class="s-ref max-w-3xl {{ $room }}" data-tool="ref-{{ $kind }}" data-src="{{ $module }}" data-mount="{{ $mount }}">
                <script type="application/json" data-tool-words>@json($card)</script>
                <script type="application/json" data-tool-data>@json($data)</script>
            </div>
            <p class="s-tool-say" role="status" data-tool-say hidden></p>
        </section>

        @foreach ((array) ($page['guide'] ?? []) as $section)
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="guide-{{ $loop->iteration }}">
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

        {{-- Its lessons to go on with (marketing's map), at the end of the teaching, before the onward links. --}}
        @include('site.courses._lessons-across', ['path' => '/'.request()->path()])

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

        {{-- The family's other pages, one tap from each. --}}
        @if ($others !== [])
            <section class="s-wrap s-sec2" aria-labelledby="others-h">
                <h2 id="others-h" class="s-kick">{{ $index['others'] }}</h2>
                <ul class="s-cards s-tool-cards">
                    @foreach ($others as $other)
                        <li>
                            <a href="{{ $other['url'] }}" class="s-card">
                                <b>{{ $other['name'] }}</b>
                                <span>{{ implode(' · ', array_slice($other['cells'], 0, 2)) }}</span>
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
