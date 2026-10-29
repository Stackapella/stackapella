{{-- Open source (/open-source; docs/open-source-plan.md, 5.2): that it is, what's open and what
     isn't, the files that make harmony line up, quiet by design, no npm, the licence in plain words,
     and how to get involved. There only with the open_source flag on. Words: lang/en/site.php. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('site.open_source.eyebrow') }}</p>
            <h1 id="page-h">{{ __('site.open_source.headline') }}</h1>
            <p class="s-lead">{{ __('site.open_source.lead') }}</p>
            <div class="s-actions">
                <a href="{{ $repo }}" class="btn-primary btn-lg" rel="noopener">{{ __('site.open_source.github') }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/selfhost" class="btn-secondary btn-lg">{{ __('site.open_source.run_it') }}</a>
            </div>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="open-h">
            <h2 id="open-h" class="s-h2">{{ __('site.open_source.open_title') }}</h2>
            <div class="s-sections">
                @foreach ($lists['open'] as [$heading, $body])
                    <article class="s-step s-v{{ $loop->index % 4 + 1 }}">
                        <h3>{{ $heading }}</h3>
                        <p>{{ $body }}</p>
                    </article>
                @endforeach
            </div>
            <p class="s-more">
                <a href="{{ \App\Support\Site\OpenSource::repo('CONTENT-LICENSE') }}" class="btn-ghost" rel="noopener">{{ __('site.open_source.content_licence') }}</a>
                <a href="{{ \App\Support\Site\OpenSource::repo('TRADEMARK.md') }}" class="btn-ghost" rel="noopener">{{ __('site.open_source.trademark') }}</a>
            </p>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="code-h">
            <h2 id="code-h" class="s-h2">{{ __('site.open_source.code_title') }}</h2>
            <p class="s-sub">{{ __('site.open_source.code_lead') }}</p>
            <div class="s-sections">
                @foreach ($code as $file)
                    <article class="s-step s-v{{ $loop->index % 4 + 1 }}">
                        <h3><a href="{{ $file['url'] }}" class="s-lk" rel="noopener" translate="no"><code style="font-size: 0.7em">{{ $file['path'] }}</code></a></h3>
                        <p>{{ $file['what'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="s-wrap s-sec2" aria-label="{{ __('site.open_source.headline') }}">
            <div class="s-sections">
                @foreach (['quiet', 'npm', 'licence'] as $part)
                    <article class="s-step s-v{{ $loop->index % 4 + 1 }}">
                        <h2>{{ __('site.open_source.'.$part.'_title') }}</h2>
                        <ul class="s-points">
                            @foreach ($lists[$part] as $point)
                                <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                            @endforeach
                        </ul>
                        @if ($part === 'licence')
                            <p><a href="{{ config('site.source.licence') }}" class="s-lk" rel="noopener">{{ __('site.open_source.licence_link') }}</a></p>
                        @elseif ($part === 'quiet')
                            <p><a href="{{ \App\Support\Site\OpenSource::repo('THIRD-PARTY-NOTICES.md') }}" class="s-lk" rel="noopener">{{ __('site.open_source.notices') }}</a></p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="involved-h">
            <div class="s-two2">
                <h2 id="involved-h" class="s-h2">{{ __('site.open_source.involved_title') }}</h2>
                <div>
                    <p class="s-sub">{{ __('site.open_source.involved') }}</p>
                    <p class="s-more">
                        <a href="{{ $repo }}/issues" class="btn-secondary" rel="noopener">{{ __('site.open_source.issues') }}</a>
                        <a href="{{ \App\Support\Site\OpenSource::repo('CONTRIBUTING.md') }}" class="btn-ghost" rel="noopener">{{ __('site.open_source.contributing') }}</a>
                        <a href="{{ \App\Support\Site\OpenSource::repo('SECURITY.md') }}" class="btn-ghost" rel="noopener">{{ __('site.open_source.security') }}</a>
                        <a href="/legal/self-hosting-terms" class="btn-ghost">{{ __('site.open_source.terms') }}</a>
                    </p>
                </div>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
