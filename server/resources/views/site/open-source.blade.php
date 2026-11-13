{{-- Open source (/open-source; docs/open-source-plan.md, 5.2), in Hillel's 2026-11-12 design
     (shots-review/review-2026-11-12/open-source-design): the headline beside its lead and buttons; what's open and
     what isn't as three tagged cards, each with its document at its foot; the licence in plain words on a dark band;
     the files that make harmony line up as a ruled list beside their heading; how it's built (quiet by design, no
     npm with its cost and gain); and one panel to get involved. There only with the open_source flag on. Words:
     lang/en/site.php. --}}
@extends('public.layout')

{{-- An arcade page in game mode (the game agent's .s-os-* pass), as the carols and the part pages are. --}}
@section('arcade')
@endsection

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-os-head" aria-labelledby="page-h">
            <div>
                <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('site.open_source.eyebrow') }}</p>
                <h1 id="page-h">{{ __('site.open_source.headline') }}</h1>
            </div>
            <div>
                <p class="s-os-lead">{{ __('site.open_source.lead') }}</p>
                <div class="s-actions">
                    <a href="{{ $repo }}" class="btn-primary btn-lg" rel="noopener">{{ __('site.open_source.github') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/selfhost" class="btn-secondary btn-lg">{{ __('site.open_source.run_it') }}</a>
                </div>
            </div>
        </section>

        {{-- What's open: the code (AAL), the content (CC BY-SA), and what stays ours, each card's document at its foot. --}}
        @php($docs = [config('site.source.licence'), \App\Support\Site\OpenSource::repo('CONTENT-LICENSE'), \App\Support\Site\OpenSource::repo('TRADEMARK.md')])
        @php($docWords = [__('site.open_source.licence_link'), __('site.open_source.content_licence'), __('site.open_source.trademark')])
        <section class="s-wrap s-sec2" aria-labelledby="open-h">
            <h2 id="open-h" class="s-h2">{{ __('site.open_source.open_title') }}</h2>
            <div class="s-os-cards">
                @foreach ($lists['open'] as [$heading, $body])
                    <article @class(['s-os-card', 's-os-closed' => $loop->last])>
                        <p class="s-os-tag"><i aria-hidden="true"></i>{{ __('site.open_source.tags.'.$loop->index) }}</p>
                        <h3>{{ $heading }}</h3>
                        <p>{{ $body }}</p>
                        <a href="{{ $docs[$loop->index] }}" class="s-os-doc" rel="noopener">{{ $docWords[$loop->index] }} <x-site.icon name="arrow" size="16" /></a>
                    </article>
                @endforeach
            </div>

            {{-- The licence in plain words: a dark band, its heading and link at left, the three points numbered. --}}
            <div class="s-ink s-os-licence">
                <div>
                    <h3>{{ __('site.open_source.licence_title') }}</h3>
                    <a href="{{ config('site.source.licence') }}" class="s-os-doc" rel="noopener">{{ __('site.open_source.licence_link') }} <x-site.icon name="arrow" size="16" /></a>
                </div>
                <ol>
                    @foreach ($lists['licence'] as $point)
                        <li><span aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><p>{{ $point }}</p></li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- The files that make harmony line up, a ruled list beside their heading (a link each, to read on GitHub). --}}
        <section class="s-wrap s-sec2 s-os-code" aria-labelledby="code-h">
            <div>
                <h2 id="code-h" class="s-h2">{{ __('site.open_source.code_title') }}</h2>
                <p class="s-sub">{{ __('site.open_source.code_lead') }}</p>
            </div>
            <ul class="s-os-files">
                @foreach ($code as $file)
                    <li>
                        <a href="{{ $file['url'] }}" rel="noopener">
                            <code translate="no">{{ $file['path'] }}</code>
                            <span>{{ $file['what'] }}</span>
                            <x-site.icon name="arrow" size="16" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="built-h">
            <h2 id="built-h" class="s-h2">{{ __('site.open_source.built_title') }}</h2>
            <div class="s-os-built">
                <article class="s-os-card">
                    <h3>{{ __('site.open_source.quiet_title') }}</h3>
                    <ul class="s-points">
                        @foreach ($lists['quiet'] as $point)
                            <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ \App\Support\Site\OpenSource::repo('THIRD-PARTY-NOTICES.md') }}" class="s-os-doc" rel="noopener">{{ __('site.open_source.notices') }} <x-site.icon name="arrow" size="16" /></a>
                </article>
                <article class="s-os-card">
                    <h3>{{ __('site.open_source.npm_title') }}</h3>
                    <ul class="s-points">
                        @foreach (array_slice($lists['npm'], 0, 2) as $point)
                            <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                        @endforeach
                    </ul>
                    {{-- The trade, said once as two halves (the list's third line, in the design's panel). --}}
                    <dl class="s-os-trade">
                        <div class="s-os-cost"><dt>{{ __('site.open_source.npm_cost.0') }}</dt><dd>{{ __('site.open_source.npm_cost.1') }}</dd></div>
                        <div class="s-os-gain"><dt>{{ __('site.open_source.npm_gain.0') }}</dt><dd>{{ __('site.open_source.npm_gain.1') }}</dd></div>
                    </dl>
                </article>
            </div>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="involved-h">
            <div class="s-os-involved">
                <div>
                    <h2 id="involved-h" class="s-h2">{{ __('site.open_source.involved_title') }}</h2>
                    <p>{{ __('site.open_source.involved') }}</p>
                </div>
                <ul class="s-os-links">
                    @foreach ([[$repo.'/issues', 'issues'], [\App\Support\Site\OpenSource::repo('CONTRIBUTING.md'), 'contributing'], [\App\Support\Site\OpenSource::repo('SECURITY.md'), 'security'], ['/legal/self-hosting-terms', 'terms']] as [$href, $word])
                        <li><a href="{{ $href }}" @if (! str_starts_with($href, '/')) rel="noopener" @endif>{{ __('site.open_source.'.$word) }} <x-site.icon name="arrow" size="16" /></a></li>
                    @endforeach
                </ul>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
