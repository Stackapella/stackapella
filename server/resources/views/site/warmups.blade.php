{{-- The free warm-ups (/warmups, docs/seo-growth-plan.md item 8): each warm-up as a card, how to warm up in a few
     minutes, a short FAQ, About Stackapella and the free tools. Words: lang/en/site.php `warmups.index`. --}}
@extends('public.layout')

{{-- The arcade screen in the game skin (<html data-gs>): the warm-ups take the tools' full game look. --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
    <x-site.json-ld :data="$ld" />
@endsection

@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))

@section('content')
    @include('site.tools._brand-row', ['onTool' => true, 'toolName' => (string) $words['eyebrow'], 'toolSlug' => 'warmups', 'ref' => 'warmups'])
    <main>
        <section class="s-wrap s-thead s-thead-hub" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ $words['eyebrow'] }}</p>
            <h1 id="page-h">{{ $words['headline'] }}</h1>
            <p class="s-lede">{{ $words['lead'] }}</p>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="page-h">
            <ul class="s-cards s-tool-cards">
                @foreach ($warmups as $warmup)
                    <li>
                        <a href="{{ $warmup['url'] }}" class="s-card">
                            <span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon name="sound" size="18" /></span>
                            <b>{{ $warmup['name'] }}</b>
                            <span>{{ $warmup['summary'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @foreach ((array) ($words['guide'] ?? []) as $section)
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="guide-{{ $loop->iteration }}">
                <h2 id="guide-{{ $loop->iteration }}">{{ $section['heading'] }}</h2>
                @foreach ((array) $section['body'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endforeach

        @include('site.tools._about', ['tie' => $words['tie'], 'tryLink' => $words['try_link']])

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
            @include('site.tools._directory', ['directory' => \App\Support\Site\SitePages::directory()])
        @endif
    </main>
    <x-site.footer />
    <script type="module" src="/js/public/tools/page.js"></script>
@endsection
