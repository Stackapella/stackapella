{{-- A reference family's index (/intervals, /chords, /voice-types; docs/seo-growth-plan.md item 4): every page in one
     table, a short guide, About Stackapella, a short FAQ and the free tools. Words: lang/en/site.php `reference`. --}}
@extends('public.layout')

{{-- The arcade screen in the game skin (<html data-gs>): the reference pages take the tools' full game look. --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
    <x-site.json-ld :data="$ld" />
@endsection

@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))

@section('content')
    @include('site.tools._brand-row', ['onTool' => true, 'toolName' => (string) $words['eyebrow'], 'toolSlug' => null, 'ref' => $kind])
    <main>
        <section class="s-wrap s-thead s-thead-hub" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ $words['eyebrow'] }}</p>
            <h1 id="page-h">{{ $words['headline'] }}</h1>
            <p class="s-lede">{{ $words['lead'] }}</p>
        </section>

        <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="page-h">
            <div class="s-tool-table"><table>
                <thead><tr>@foreach ((array) $words['table']['head'] as $cell)<th scope="col">{{ $cell }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr><th scope="row"><a href="{{ $row['url'] }}" class="s-lk">{{ $row['name'] }}</a></th>@foreach ($row['cells'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table></div>
        </section>

        @foreach ((array) ($words['guide'] ?? []) as $section)
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="guide-{{ $loop->iteration }}">
                <h2 id="guide-{{ $loop->iteration }}">{{ $section['heading'] }}</h2>
                @foreach ((array) $section['body'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endforeach

        {{-- Its lessons to go on with (marketing's map), at the end of the teaching, before About. --}}
        @include('site.courses._lessons-across', ['path' => '/'.request()->path()])

        @include('site.tools._about', ['tie' => $ix['tie'], 'tryLink' => $ix['try_link']])

        @if (! empty($words['faq']))
            <section class="s-wrap s-sec" aria-labelledby="faq-h">
                <h2 id="faq-h" class="s-kick">{{ $ix['faq'] }}</h2>
                <div class="s-faq s-faq-round">
                    @foreach ((array) $words['faq'] as [$question, $answer])
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
