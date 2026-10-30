{{-- What's new (/whats-new): resources/whats-new/en.json, newest first, one change after another,
     each linkable by its title. No dates shown (Hillel: "remove dates from what's new, we'll likely add
     features in bursts"); each entry keeps its date in the file, for the order. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('meta.brand') }}</p>
            <h1 id="page-h">{{ __('site.whats_new.headline') }}</h1>
            <p class="s-lede">{{ __('site.whats_new.lead') }}</p>
        </section>
        <section class="s-wrap s-sec" aria-label="{{ __('site.whats_new.headline') }}">
            <ol class="s-tl-list">
                @foreach ($entries as $entry)
                    @php($anchor = \Illuminate\Support\Str::slug($entry['title']))
                    <li id="{{ $anchor }}">
                        <h2><a href="#{{ $anchor }}">{{ $entry['title'] }}</a></h2>
                        <p>{{ $entry['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    </main>
    <x-site.footer />
@endsection
