{{-- One page of the user guide (docs/user-guide-plan.md): the contents and search beside it, the
     page's sections (numbered steps, screenshots in the reader's theme, tips), "On this page", then
     Contact us and the previous and next pages. On a phone the contents and "On this page" fold. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

{{-- Calm: people read here (the arcade-versus-calm rule). --}}
@section('calm', true)

@section('head')
    @include('pages._head')
@endsection

@section('content')
    @include('pages._header')
    <main class="s-wrap s-guide">
        <header class="s-guide-head">
            <p class="s-eyebrow"><a href="/help" class="s-back">{{ __('pages.help.eyebrow') }}</a></p>
            <h1 id="page-h">{{ $title }}</h1>
            <p class="s-lead">{{ $meta['description'] }}</p>
        </header>

        <div class="s-guide-rail">
            @include('pages.help._search', ['id' => 'help-search'])
            <details class="s-guide-fold" data-open-wide>
                <summary>{{ __('pages.help.contents') }}<x-site.icon name="chevron" size="16" /></summary>
                @include('pages.help._nav', ['current' => $slug])
            </details>
        </div>

        @if (count($sections) > 1)
            <details class="s-guide-fold s-guide-toc" data-open-wide>
                <summary>{{ __('pages.help.on_this_page') }}<x-site.icon name="chevron" size="16" /></summary>
                <nav aria-label="{{ __('pages.help.on_this_page') }}">
                    <ul>
                        @foreach ($sections as $section)
                            <li><a href="#{{ $section['id'] }}">{{ $section['heading'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            </details>
        @endif

        <div class="s-guide-body">
            <div class="s-prose">
                @foreach ($sections as $section)
                    @if ($section['fold'])
                        <details class="s-fold" id="{{ $section['id'] }}">
                            <summary><h2>{{ $section['heading'] }}</h2><x-site.icon name="chevron" size="18" /></summary>
                    @else
                        <h2 id="{{ $section['id'] }}">{{ $section['heading'] }}</h2>
                    @endif
                    {{-- A phone's tall screenshot stands beside the section's words on a wide screen (after them on a phone). --}}
                    @php($tall = collect($section['blocks'])->filter(fn (array $b): bool => is_array($b['shot'] ?? null) && $b['shot']['light']['height'] > $b['shot']['light']['width'])->values())
                    @if ($tall->isNotEmpty())
                        <div class="s-stepshot"><div>
                    @endif
                    @foreach ($section['blocks'] as $block)
                        @continue($tall->contains($block))
                        @if ($block['type'] === 'list')
                            @if ($section['steps'])
                                <ol start="{{ $block['start'] }}">
                                    @foreach ($block['items'] as $line)
                                        <li>{{ \App\Support\Site\Prose::inline($line) }}</li>
                                    @endforeach
                                </ol>
                            @else
                                <ul>
                                    @foreach ($block['items'] as $line)
                                        <li>{{ \App\Support\Site\Prose::inline($line) }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        @elseif ($block['type'] === 'links')
                            <p class="s-see">
                                @foreach ($block['links'] as $link)
                                    <a href="{{ $link['href'] }}" class="btn-secondary btn-sm">{{ $link['text'] }} <x-site.icon name="arrow" size="15" /></a>
                                @endforeach
                            </p>
                        @elseif ($block['type'] === 'tip')
                            <p class="s-tip"><x-site.icon name="info" size="18" /><span>{{ \App\Support\Site\Prose::inline($block['text']) }}</span></p>
                        @else
                            @include('pages.help._shot', ['shot' => $block['shot'], 'alt' => $block['alt']])
                        @endif
                    @endforeach
                    @if ($tall->isNotEmpty())
                        </div><div class="s-stepshot-pic">
                            @foreach ($tall as $block)
                                @include('pages.help._shot', ['shot' => $block['shot'], 'alt' => $block['alt']])
                            @endforeach
                        </div></div>
                    @endif
                    @if ($section['fold'])
                        </details>
                    @endif
                @endforeach
            </div>

            <div class="s-step s-v4 s-try">
                <p>{{ __('pages.help.contact') }}</p>
                <p class="s-share-btns"><a href="{{ $contactUrl }}" class="btn-secondary">{{ __('pages.help.contact_link') }}</a></p>
                <p>{{ __('pages.help.contact_email', ['email' => config('site.contact_email')]) }}</p>
            </div>

            @if ($pager['prev'] || $pager['next'])
                <nav class="s-pager" aria-label="{{ __('pages.help.pager') }}">
                    @if ($pager['prev'])
                        <a href="/help/{{ $pager['prev']['slug'] }}" class="s-card s-pager-prev" rel="prev"><span>{{ __('pages.help.prev') }}</span><b>{{ $pager['prev']['title'] }}</b></a>
                    @endif
                    @if ($pager['next'])
                        <a href="/help/{{ $pager['next']['slug'] }}" class="s-card s-pager-next" rel="next"><span>{{ __('pages.help.next') }}</span><b>{{ $pager['next']['title'] }}</b></a>
                    @endif
                </nav>
            @endif
        </div>
    </main>
    @include('pages._footer')
    <script type="module" src="/js/public/help-search.js"></script>
@endsection
