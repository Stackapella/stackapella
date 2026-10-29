{{-- Self-host (/selfhost; docs/open-source-plan.md, 5.3): what you need, how to install it, what you
     get with SELF_HOSTED and what isn't included, what a fresh install connects to, what's yours to run, choirs and schools, and whether you should.
     There only with the open_source flag on. Words: lang/en/site.php. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('site.selfhost.eyebrow') }}</p>
            <h1 id="page-h">{{ __('site.selfhost.headline') }}</h1>
            <p class="s-lead">{{ __('site.selfhost.lead') }}</p>
            <div class="s-actions">
                <a href="{{ $repo }}" class="btn-primary btn-lg" rel="noopener">{{ __('site.selfhost.github') }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/open-source" class="btn-secondary btn-lg">{{ __('site.selfhost.open_source') }}</a>
            </div>
        </section>

        <section class="s-wrap s-sec2" aria-label="{{ __('site.selfhost.headline') }}">
            <div class="s-sections">
                @foreach (['need', 'install', 'unlocked', 'missing', 'connects', 'yours'] as $part)
                    <article class="s-step s-v{{ $loop->index % 4 + 1 }}">
                        <h2>{{ __('site.selfhost.'.$part.'_title') }}</h2>
                        @if ($part === 'install')
                            <ol class="s-points">
                                @foreach ($lists['install'] as $step)
                                    <li translate="no"><b>{{ $loop->iteration }}</b>{{ $step }}</li>
                                @endforeach
                            </ol>
                        @else
                            <ul class="s-points">
                                @foreach ($lists[$part] as $point)
                                    <li><x-site.icon :name="$part === 'missing' ? 'minus' : 'check'" size="18" />{{ $point }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </article>
                @endforeach
                @foreach (['optional', 'choirs', 'should'] as $part)
                    <article class="s-step s-v{{ ($loop->index + 1) % 4 + 1 }}">
                        <h2>{{ __('site.selfhost.'.$part.'_title') }}</h2>
                        <p>{{ __('site.selfhost.'.$part) }}</p>
                    </article>
                @endforeach
            </div>
            <p class="s-more">
                <a href="/legal/self-hosting-terms" class="btn-ghost">{{ __('site.selfhost.terms') }}</a>
                <a href="{{ $repo }}#running-it-yourself" class="btn-ghost" rel="noopener">{{ __('site.selfhost.readme') }}</a>
                <a href="{{ \App\Support\Site\OpenSource::repo('THIRD-PARTY-NOTICES.md') }}" class="btn-ghost" rel="noopener">{{ __('site.selfhost.notices') }}</a>
            </p>
        </section>
    </main>
    <x-site.footer />
@endsection
