{{-- What's new (/whats-new): resources/whats-new/en.json, newest first, as a timeline (the redesign):
     each day's date held at the side while its changes scroll by, each change linkable. --}}
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
            @foreach (collect($entries)->groupBy('date') as $date => $day)
                <section class="s-tl-day" aria-labelledby="day-{{ $date }}">
                    <h2 id="day-{{ $date }}" class="s-tl-date"><time datetime="{{ $date }}">{{ $day[0]['day'] }}</time></h2>
                    <ol class="s-tl-list">
                        @foreach ($day as $entry)
                            <li id="{{ $date }}-{{ $loop->iteration }}">
                                <h3><a href="#{{ $date }}-{{ $loop->iteration }}">{{ $entry['title'] }}</a></h3>
                                <p>{{ $entry['body'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </section>
    </main>
    <x-site.footer />
@endsection
