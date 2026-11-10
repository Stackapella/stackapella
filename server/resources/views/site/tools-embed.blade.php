{{-- /tools/embed (docs/seo-growth-plan.md, item 2): a free tool on a choir's, school's or teacher's own website. Pick
     the tool and its look, see it live, copy one line (js/public/tools/embed-maker.js fills the preview and the code
     from $maker). Then why choirs use it, the questions, and the About panel. Words: site.tools.embed. --}}
@extends('public.layout')

@section('arcade')
@endsection

@section('head')
    @include('pages._head')
@endsection

@section('content')
    @include('site.tools._brand-row')
    <main>
        <section class="s-wrap s-thead s-thead-hub" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ $words['eyebrow'] }}</p>
            <h1 id="page-h">{{ $words['headline'] }}</h1>
            <p class="s-lede">{{ $words['lead'] }}</p>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="maker-h">
            <div class="s-emaker" data-embed-maker>
                <script type="application/json" data-embed-maker-data>@json($maker)</script>
                <h2 id="maker-h" class="s-kick">{{ $words['maker'] }}</h2>
                <div class="s-emaker-pick">
                    <label><span class="label">{{ $words['tool'] }}</span>
                        <select class="input" data-embed-tool>
                            @foreach ($maker['tools'] as $tool)
                                <option value="{{ $tool['slug'] }}">{{ $tool['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label><span class="label">{{ $words['look'] }}</span>
                        <select class="input" data-embed-theme>
                            <option value="">{{ $words['look_visitor'] }}</option>
                            @foreach ($maker['themes'] as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="s-emaker-game"><input type="checkbox" data-embed-game> {{ $words['game'] }}</label>
                </div>
                <div class="s-emaker-out">
                    <figure class="s-emaker-preview">
                        <figcaption class="label">{{ $words['preview'] }}</figcaption>
                        <iframe data-embed-preview title="" width="100%" height="620" loading="lazy" allow="microphone"></iframe>
                    </figure>
                    <div class="s-emaker-code">
                        <p class="label" id="code-l">{{ $words['code'] }}</p>
                        <textarea class="input" rows="5" readonly aria-labelledby="code-l" data-embed-code spellcheck="false"></textarea>
                        <p class="help">{{ $words['code_hint'] }}</p>
                        <p class="help" data-embed-mic hidden>{{ $words['mic'] }}</p>
                        <button type="button" class="btn-primary" data-embed-copy>{{ $words['copy'] }}</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="s-wrap s-sec2" aria-labelledby="why-h">
            <h2 id="why-h" class="s-kick">{{ $words['why'] }}</h2>
            <ul class="s-about-ben s-emaker-why">
                @foreach ($words['points'] as [$point, $line])
                    <li><span class="s-who-ic s-v{{ $loop->iteration }}" aria-hidden="true"><x-site.icon :name="['sound', 'spark', 'grid'][$loop->index]" size="20" /></span><div><b>{{ $point }}</b><span>{{ $line }}</span></div></li>
                @endforeach
            </ul>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <h2 id="faq-h" class="s-kick">{{ __('site.tools.index.faq') }}</h2>
            <div class="s-faq s-faq-round">
                @foreach ($words['faq'] as [$question, $answer])
                    <details>
                        <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        @include('site.tools._about', ['tie' => $words['tie'], 'tryLink' => $words['try_link']])
    </main>
    <x-site.footer />
    <script type="module" src="/js/public/tools/page.js"></script>
    <script type="module" src="/js/public/tools/embed-maker.js"></script>
@endsection
