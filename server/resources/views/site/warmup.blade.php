{{-- One free warm-up (/warmups/<warm-up>, docs/seo-growth-plan.md item 8): the player above the fold
     (js/public/tools/page.js mounts js/public/tools/warmup-player.js on the stage, with the courses lead's entry and
     its course step in data-tool-data, so it needs no request), its instruction and stop line on the first screen,
     then the searched heading, how to sing it, a short guide, the other warm-ups, About Stackapella, a short FAQ and
     the free tools. Words: lang/en/site.php `warmups`; the instruction and stop line are the list's (course/warmups.json). --}}
@extends('public.layout')

{{-- The arcade screen in the game skin (<html data-gs>): the warm-ups take the tools' full game look. --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
    @include('site.tools._preloads', ['preloads' => \App\Support\Site\ToolPreloads::for('/js/public/tools/warmup-player.js')])
    <x-site.json-ld :data="$ld" />
@endsection

@php($ix = (array) \Illuminate\Support\Facades\Lang::get('site.tools.index'))

@section('content')
    {{-- The tools' brand row, the warm-up's name beside the logo; its key slot takes the player's tempo. --}}
    @include('site.tools._brand-row', ['onTool' => true, 'toolName' => (string) $page['name'], 'toolSlug' => 'warmups-'.$page['slug'], 'ref' => 'warmups', 'trail' => [[(string) $index['eyebrow'], '/warmups'], [(string) $page['name']]]])
    <main>

        <section class="s-wrap" aria-label="{{ $page['name'] }}">
            <div class="s-tool" data-tool="warmups-{{ $page['slug'] }}" data-src="/js/public/tools/warmup-player.js" data-mount="mountWarmupPlayer">
                <script type="application/json" data-tool-words>@json($card)</script>
                <script type="application/json" data-tool-data>@json($data)</script>
            </div>
            <p class="s-tool-say" role="status" data-tool-say hidden></p>
        </section>

        <section class="s-wrap s-sec2 s-tool-more" aria-labelledby="page-h">
            <p class="s-teyebrow">{{ $page['eyebrow'] }}</p>
            <h1 id="page-h">{{ $page['headline'] }}</h1>
            <p class="s-lede s-tool-lede">{{ $page['lead'] }}</p>
            <h2 class="s-kick">{{ $ix['how'] }}</h2>
            <ol class="s-tool-how">
                @foreach ((array) $page['how'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ol>
        </section>

        @foreach ((array) ($page['guide'] ?? []) as $section)
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="guide-{{ $loop->iteration }}">
                <h2 id="guide-{{ $loop->iteration }}">{{ $section['heading'] }}</h2>
                @foreach ((array) $section['body'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
        @endforeach

        {{-- The other warm-ups, so a short routine is one tap from each. --}}
        @if ($others !== [])
            <section class="s-wrap s-sec2" aria-labelledby="others-h">
                <h2 id="others-h" class="s-kick">{{ $index['others'] }}</h2>
                <ul class="s-cards s-tool-cards">
                    @foreach ($others as $other)
                        <li>
                            <a href="{{ $other['url'] }}" class="s-card">
                                <span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon name="sound" size="18" /></span>
                                <b>{{ $other['name'] }}</b>
                                <span>{{ $other['summary'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('site.tools._about', ['tie' => $page['tie'], 'tryLink' => $index['try_link']])

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
