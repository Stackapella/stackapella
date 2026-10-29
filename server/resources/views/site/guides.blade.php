{{-- The guides (/guides), in the redesign's sections: one guide to start with beside the four-voice
     player, then the rest in groups, each its one line, and the last word on the band. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php($bySlug = collect($cards)->keyBy('slug'))
@php($featured = $bySlug->get(__('pages.guides.featured')))
@php($hero = \App\Support\Site\SitePages::draw()['hero'])

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('pages.guides.eyebrow') }}</p>
            <h1 id="page-h">{{ __('pages.guides.headline') }}</h1>
            <p class="s-lede">{{ __('pages.guides.lead') }}</p>
        </section>

        @if ($featured !== null)
            <section class="s-wrap s-sec" aria-labelledby="featured-h">
                <div class="s-split">
                    <div>
                        <p class="s-kick">{{ __('pages.guides.featured_kick') }}</p>
                        <h2 id="featured-h" class="s-title">{{ $featured['name'] }}</h2>
                        <p class="s-lede">{{ $featured['summary'] }}</p>
                        <a href="{{ $featured['url'] }}" class="s-arrow">{{ __('pages.guides.read') }} <x-site.icon name="arrow" size="16" /></a>
                    </div>
                    @if ($hero !== null)
                        <x-site.mixer :song="$hero" id="guides-mixer" />
                    @endif
                </div>
            </section>
        @endif

        @foreach ((array) \Illuminate\Support\Facades\Lang::get('pages.guides.groups') as [$name, $slugs])
            @php($group = collect($slugs)->map(fn (string $slug) => $bySlug->get($slug))->filter()->values()->all())
            @continue($group === [])
            <section class="s-wrap s-sec" aria-labelledby="guides-{{ $loop->iteration }}">
                <h2 id="guides-{{ $loop->iteration }}" class="s-kick">{{ $name }}</h2>
                <div class="s-cards-row" style="--n: {{ count($group) }}">@include('site._cards', ['cards' => $group])</div>
            </section>
        @endforeach

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ __('pages.site.final_title') }}</h2>
                <p>{{ __('pages.site.final_body') }}</p>
                <p class="s-final-btns"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
@endsection
