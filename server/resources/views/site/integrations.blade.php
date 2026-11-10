{{-- The integrations' hub (/integrations; IntegrationPagesController): a card for each other app, each saying it
     works beside Stackapella today and that its connection is coming, and who makes them. A top-level hub, so no
     trail. Words: lang/en/site.php integrations. --}}
@extends('public.layout')

@section('arcade')
@endsection

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="int">
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <p class="s-eyebrow">{{ __('site.integrations.index.eyebrow') }}</p>
            <h1 id="page-h">{{ __('site.integrations.index.headline') }}</h1>
            <p class="s-lead">{{ __('site.integrations.index.lead') }}</p>
        </section>
        <section class="s-wrap s-sec" aria-label="{{ __('site.integrations.index.eyebrow') }}">
            <ul class="int-hub">
                @foreach ($pages as $item)
                    @include('site._integration-card', ['slug' => $item['slug'], 'item' => $item])
                @endforeach
            </ul>
            <p class="int-maker">{{ __('site.integrations.index.maker') }} <a href="/about" class="s-lk">{{ __('site.integrations.index.about') }}</a></p>
        </section>
    </main>
    <x-site.footer />
@endsection
