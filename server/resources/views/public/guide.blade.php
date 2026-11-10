{{-- A guide at /guides/<slug> (DocumentPagesController::guide; the design round's direction): its
     words in a reading column, its numbered steps as a path in the parts' colours, the guide's own
     song to play where the steps begin (a free song only), and beside it the song to try and the
     next guides. Words: resources/guides/en; the frame: lang/en/pages.php `guides`. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

{{-- Calm: people read here (the arcade-versus-calm rule). --}}
@section('calm', true)

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="article">
    <meta property="og:image" content="{{ \App\Support\Site\SitePages::image($meta['url']) }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

{{-- Where the four parts' ranges go: before the heading after the section the guide names (its end, if that's last). --}}
@php($rangesFrom = $guide['ranges'] !== null ? collect($guide['blocks'])->search(fn (array $b): bool => $b['type'] === 'heading' && $b['text'] === $guide['ranges']) : false)
@php($rangesAt = $rangesFrom === false ? null : (collect($guide['blocks'])->keys()->first(fn (int $i): bool => $i > $rangesFrom && $guide['blocks'][$i]['type'] === 'heading') ?? count($guide['blocks'])))
@php($rangesPanel = ['parts' => $guide['parts'], 'song' => $guide['song']['title'] ?? ''])
@php($guideDemo = \Illuminate\Support\Facades\Lang::has('pages.guides.demos.'.$guide['slug']) ? (array) \Illuminate\Support\Facades\Lang::get('pages.guides.demos.'.$guide['slug']) : null)

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <x-site.crumbs :trail="[[__('pages.guides.eyebrow'), '/guides'], [$guide['title']]]" :ld="true" />
            <h1 id="page-h">{{ $guide['title'] }}</h1>
            <p class="s-lede">{{ $guide['description'] }}</p>
            {{-- The guides choir singers find in the season point to the carols (site.carols.links, by guide). --}}
            @if (trans()->has('site.carols.links.'.$guide['slug']))
                @include('site._carols-link', ['line' => __('site.carols.links.'.$guide['slug'])])
            @endif
        </section>

        <div class="s-wrap s-greading">
            <article class="s-gread">
                <div class="s-prose">
                    @foreach ($guide['blocks'] as $block)
                        @if ($loop->index === $rangesAt)
                            @include('site._guide-ranges', $rangesPanel)
                        @endif
                        {{-- The song's opening line plays where the steps begin, so you can try each one. --}}
                        {{-- Where the steps begin: the guide's live demo in the shared slot when it has one
                             (pages.guides.demos.<slug>), else its song's opening line to play. --}}
                        @if ($block['type'] === 'heading' && $block['step'] === 1)
                            @if ($guideDemo !== null)
                                {{-- Out of the prose, so its words keep the slot's own colours. --}}
                                </div><div class="s-gdemo">@include('site._demo-section', ['page' => ['demo' => $guideDemo]])</div><div class="s-prose">
                            @elseif ($line !== null)
                                <div class="s-gmixer s-narrow"><x-site.mixer :song="$line" id="guide-mixer" /></div>
                            @endif
                        @endif
                        @if ($block['type'] === 'heading' && $block['step'] !== null)
                            <h2 class="s-gstep"><span class="s-disc s-v{{ ($block['step'] - 1) % 4 + 1 }}" aria-hidden="true">{{ $block['step'] }}</span><span>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</span></h2>
                        @elseif ($block['type'] === 'heading')
                            @if (($block['level'] ?? 2) <= 2)
                                <h2>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</h2>
                            @else
                                <h3>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</h3>
                            @endif
                        @elseif ($block['type'] === 'list')
                            <ul>
                                @foreach ($block['items'] ?? [] as $item)
                                    <li>{{ \App\Support\Site\Prose::inline((string) $item) }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</p>
                        @endif
                    @endforeach
                    @if ($rangesAt === count($guide['blocks']))
                        @include('site._guide-ranges', $rangesPanel)
                    @endif
                </div>
                @if ($guide['faq'] !== [])
                    <section class="s-gfaq" aria-labelledby="faq-h">
                        <h2 id="faq-h">{{ __('pages.guides.faq_title') }}</h2>
                        <div class="s-faq s-faq-round">
                            @foreach ($guide['faq'] as [$question, $answer])
                                <details @if ($loop->first) open @endif>
                                    <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                                    <p>{{ $answer }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                    <x-site.json-ld :data="[['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $guide['faq'])]]" />
                @endif
            </article>

            <aside class="s-gside" aria-label="{{ __('pages.guides.try') }}">
                @if ($guide['song'] !== null)
                    <div class="s-gcard">
                        <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                        <p class="s-gcard-k">{{ __('pages.guides.try') }}</p>
                        <h2 translate="no">{{ $guide['song']['title'] }}</h2>
                        <p>{{ collect($guide['song']['parts'])->pluck('name')->join(', ') }}</p>
                        <a href="{{ $guide['song']['startUrl'] }}" class="btn-primary">{{ __('pages.guides.start') }} <x-site.icon name="arrow" size="16" /></a>
                        <a href="{{ $guide['song']['pageUrl'] }}" class="link-quiet">{{ __('pages.guides.song') }}</a>
                    </div>
                @endif
                {{-- Its lessons to go on with (marketing's map), at the end of the guide, before the next guides. --}}
                @include('site.courses._lessons-across', ['path' => '/'.request()->path(), 'nested' => true])
                @if ($guide['next'] !== [])
                    <nav class="s-gnext" aria-labelledby="next-h">
                        <h2 id="next-h">{{ __('pages.guides.next') }}</h2>
                        <ul>
                            @foreach ($guide['next'] as $other)
                                <li><a href="/guides/{{ $other['slug'] }}">{{ $other['title'] }}</a></li>
                            @endforeach
                        </ul>
                        <a href="/guides" class="btn-secondary btn-sm">{{ __('pages.guides.all') }}</a>
                    </nav>
                @endif
            </aside>
        </div>

        <section class="s-wrap" aria-labelledby="end-h">
            <div class="s-end">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <div>
                    <h2 id="end-h">{{ __('pages.site.final_title') }}</h2>
                    <p>{{ __('pages.site.final_body') }}</p>
                </div>
                <a href="{{ \App\Support\Http\Hosts::app('/learn/1-comfortable') }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    @if ($line !== null)
        <script type="module" src="/js/public/mixer.js"></script>
    @endif
    @if ($rangesAt !== null)
        <script type="module" src="/js/public/guide-ranges.js"></script>
    @endif
@endsection
