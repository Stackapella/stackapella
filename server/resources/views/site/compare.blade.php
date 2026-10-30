{{-- The comparison pages' hub (/compare; docs/compare-pages-plan.md): one card for each, dated.
     Unlisted until the compare_pages switch. Words: lang/en/site.php compare. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="cmp">
        <section class="s-wrap s-featurehead cmp-head" aria-labelledby="page-h">
            <p class="s-eyebrow">{{ __('site.compare.index.eyebrow') }}</p>
            <h1 id="page-h">{{ __('site.compare.index.headline') }}</h1>
            <p class="cmp-asof">{{ $common['asof'] }}</p>
            <p class="s-lede">{{ __('site.compare.index.lead') }}</p>
        </section>
        <section class="s-wrap s-sec">
            <ul class="s-cards cmp-hub">
                @foreach ($pages as $item)
                    <li><a href="/compare/{{ $item['slug'] }}" class="s-card"><b>{{ $item['headline'] }}</b><span>{{ $item['summary'] }}</span></a></li>
                @endforeach
            </ul>
            <p class="cmp-note">{{ $common['names'] }}</p>
        </section>
    </main>
    <x-site.footer />
@endsection
