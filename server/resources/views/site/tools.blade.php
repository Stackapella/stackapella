{{-- The free tools' hub (/tools, docs/tools-plan.md): every built tool as a card, and one way into the app
     ("Open the app" instead for a visitor who's signed in). Words: lang/en/site.php `tools.index`. --}}
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
        <section class="s-wrap s-thead s-thead-hub" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ $words['eyebrow'] }}</p>
            <h1 id="page-h">{{ $words['headline'] }}</h1>
            <p class="s-lede">{{ $words['lead'] }}</p>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="page-h">
            <ul class="s-cards s-tool-cards">
                @foreach ($tools as $tool)
                    <li>
                        <a href="{{ $tool['url'] }}" class="s-card">
                            <span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon :name="$tool['icon']" size="18" /></span>
                            <b>{{ $tool['name'] }}</b>
                            <span>{{ $tool['summary'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="s-tool-related"><a href="/warmups" class="s-lk">{{ $words['warmups'] }} <x-site.icon name="arrow" size="16" /></a></p>
        </section>
        @include('site.tools._about', ['tie' => $words['tie'], 'tryLink' => $words['try_link']])
    </main>
    <x-site.footer />
    {{-- The tools' own service worker (no tool on this page: page.js only registers it). --}}
    <script type="module" src="/js/public/tools/page.js"></script>
@endsection
