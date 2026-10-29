{{-- A tune shared as an idea (/ideas/<id>, behind `ideas`; docs/your-tune-plan.md step 3): who
     wrote it and their note, each part with its range and how many voices it has, who sang what,
     and Sing a part into the app. No single voice's audio is ever here. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-hero s-page s-doc-head" aria-labelledby="page-h">
            <p class="s-eyebrow">{{ $idea['complete'] ? __('pages.together.idea_complete') : __('pages.together.idea_eyebrow') }}</p>
            <h1 id="page-h" translate="no">{{ $meta['title'] }}</h1>
            <div class="s-coach-top s-sung">
                @include('public._avatar', ['coach' => $idea['writer'], 'size' => 36])
                <span><b translate="no">{{ __('pages.together.by', ['name' => $idea['writer']['name']]) }}</b></span>
            </div>
            @if ($idea['note'])
                <p class="s-lead" translate="no">{{ $idea['note'] }}</p>
            @endif
        </section>
        <section class="s-wrap s-sec s-doc" style="padding-top: 24px" aria-labelledby="parts-h">
            <h2 id="parts-h" class="s-h2" style="font-size: 26px">{{ __('pages.together.parts') }}</h2>
            <ul class="s-parts s-idea-parts">
                @foreach ($idea['parts'] as $part)
                    <li class="s-v{{ min($loop->iteration, 4) }}">
                        <span class="s-disc" aria-hidden="true">{{ mb_substr($part['name'], 0, 1) }}</span>
                        <b>{{ $part['name'] }}</b>
                        <span>{{ __('pages.together.range', ['low' => \App\Support\Pages\SongPage::note((int) $part['range']['low']), 'high' => \App\Support\Pages\SongPage::note((int) $part['range']['high'])]) }}</span>
                        @if ((int) $part['voices'] === 0)
                            <span class="s-badge s-badge-free">{{ __('pages.together.open') }}</span>
                        @else
                            <span>{{ trans_choice('pages.together.voices', (int) $part['voices'], ['count' => (int) $part['voices']]) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="s-step s-v2 s-try">
                <p>{{ __('pages.together.sing_note') }}</p>
                <p class="s-share-btns"><a href="{{ $links['sing'] }}" class="btn-primary btn-lg">{{ __('pages.together.sing') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
            @if ($idea['credits'] !== [])
                <h2 class="s-h2" style="font-size: 26px; margin-top: 40px">{{ __('pages.together.sung_by') }}</h2>
                @include('public._credits', ['credits' => $idea['credits'], 'parts' => $idea['parts']])
            @endif
        </section>
    </main>
    <x-site.footer />
@endsection
