{{-- One comparison page (/compare/<slug>; docs/compare-pages-plan.md): Stackapella and another product,
     dated, in flat words for both. Who each is for, the same ten rows on every page (each of their
     cells numbered to its source), where each fits better (theirs first), moving over where it's
     useful, questions with FAQ data, Start singing, and the sources with the names line. No logos or
     pictures of their apps. Unlisted until the compare_pages switch. Words: lang/en/site.php compare. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php($cols = (array) $page['columns'])
@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="cmp">
        <section class="s-wrap s-featurehead cmp-head" aria-labelledby="page-h">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/compare">{{ __('site.compare.index.eyebrow') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <h1 id="page-h">{{ $page['headline'] }}</h1>
            <p class="cmp-asof">{{ $common['asof'] }}</p>
            <p class="s-lede">{{ $page['lead'] }}</p>
        </section>

        <section class="s-wrap s-sec cmp-for" aria-labelledby="for-h">
            <h2 id="for-h" class="s-kick">{{ $common['titles']['for'] }}</h2>
            <div class="cmp-two">
                <div class="cmp-card">
                    <h3>{{ str_replace(':name', $page['name'], $common['titles']['for_them']) }}</h3>
                    <ul class="s-ticks">@foreach ((array) $page['them_for'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                </div>
                <div class="cmp-card cmp-us">
                    <h3>{{ $common['titles']['for_us'] }}</h3>
                    <ul class="s-ticks">@foreach ((array) $page['us_for'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                </div>
            </div>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="table-h">
            <h2 id="table-h" class="s-title">{{ $common['titles']['table'] }}</h2>
            <div class="cmp-tablewrap">
                <table class="cmp-table cmp-cols-{{ count($cols) + 1 }}">
                    <thead>
                        <tr>
                            <td></td>
                            @foreach ($cols as $col)
                                <th scope="col" translate="no">{{ $col }}</th>
                            @endforeach
                            <th scope="col" class="cmp-us" translate="no">{{ $common['us'] }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ((array) $common['rows'] as $row => $label)
                            <tr>
                                <th scope="row">{{ $label }}</th>
                                @foreach ((array) $page['cells'][$row] as [$text, $sources])
                                    <td data-col="{{ $cols[$loop->index] ?? '' }}">{{ $text }}@foreach ((array) $sources as $n)<a href="#source-{{ $n }}" class="cmp-src" aria-label="{{ str_replace(':n', (string) $n, $common['source']) }}">{{ $n }}</a>@endforeach</td>
                                @endforeach
                                <td class="cmp-us" data-col="{{ $common['us'] }}">{{ $common['cells'][$row] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="cmp-note">{{ $common['not_stated'] }}</p>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="fits-h">
            <h2 id="fits-h" class="s-title">{{ $common['titles']['fits'] }}</h2>
            <div class="cmp-two">
                <div class="cmp-card">
                    <h3>{{ str_replace(':name', $page['name'], $common['titles']['fits_them']) }}</h3>
                    <ul class="s-ticks">@foreach ((array) $page['fits_them'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                </div>
                <div class="cmp-card cmp-us">
                    <h3>{{ $common['titles']['fits_us'] }}</h3>
                    <ul class="s-ticks">@foreach ((array) $page['fits_us'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                </div>
            </div>
        </section>

        @isset($page['moving'])
            <section class="s-wrap s-sec" aria-labelledby="moving-h">
                <h2 id="moving-h" class="s-title">{{ $common['titles']['moving'] }}</h2>
                <p class="s-lede">{{ $page['moving'] }}</p>
            </section>
        @endisset

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <h2 id="faq-h" class="s-title">{{ $common['titles']['faq'] }}</h2>
            @include('site.courses._faq')
        </section>

        <section class="s-ink" aria-labelledby="try-h">
            <div class="s-wrap s-sec">
                <h2 id="try-h" class="s-title">{{ $common['titles']['try'] }}</h2>
                <p class="s-lede">{{ $common['try_body'] }}</p>
                <p class="s-actions-l"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>

        <section class="s-wrap s-sec cmp-sources" aria-labelledby="sources-h">
            <h2 id="sources-h" class="s-kick">{{ $common['titles']['sources'] }}</h2>
            <p class="cmp-note">{{ $common['seen'] }}</p>
            <ol>
                @foreach ((array) $page['sources'] as [$label, $url])
                    <li id="source-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener nofollow">{{ $label }}</a></li>
                @endforeach
            </ol>
            <p class="cmp-note">{{ $common['names'] }}</p>
        </section>
    </main>
    <x-site.footer />
@endsection
