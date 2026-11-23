{{-- For choir directors (/for/choir-directors), radically shorter (Hillel, 2026-11-23, from the consultant's concept;
     Saul: "if a section doesn't help someone choose a path, cut or fold it"): the promise beside the director's view as a
     live demo (made-up singers, no faces), one way in with the email beside it, then the four steps, "No group yet?" with
     the part pages, three questions, and the last word. Words: lang/en/site.php `for_directors`, through Teams so the
     plus_teams and choir_seats switches change them (a seat per singer while seats are on). --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php($start = \App\Support\Http\Hosts::app('/groups?start=1&ref='.$page['group_start']))
@php($email = $page['try'][1])
@php($faq = (array) \App\Support\Site\Teams::words('site.for_directors.faq'))

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="s-dir">
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div class="s-split">
                <div>
                    <x-site.crumbs :trail="[[__('pages.site.who_crumb'), '/features#who-h'], [$page['name']]]" :ld="true" />
                    {{-- Game mode's kicker (Hillel's 90s design): a choir group is two players' co-op. --}}
                    <p class="s-kick s-skin-g">{{ __('site.game.co_op_kick') }}</p>
                    <h1 id="page-h">{{ __('site.for_directors.headline') }}</h1>
                    <p class="s-lede">{{ __('site.for_directors.lead') }}</p>
                    <div class="s-actions s-actions-row">
                        <a href="{{ $start }}" class="btn-primary btn-lg"><x-site.skin :game="__('site.game.co_op_start')" :serious="__('site.for_directors.start')" /> <x-site.icon name="arrow" size="18" /></a>
                        <a href="{{ $email }}" class="btn-secondary btn-lg">{{ __('site.for_directors.email') }}</a>
                    </div>
                    <p class="s-dir-note">{{ \App\Support\Site\Teams::text('site.for_directors.note') }} <a href="/pricing" class="s-lk">{{ __('site.for_directors.pricing') }}</a></p>
                </div>
                <div class="s-dir-view">
                    @include('site._choir-board', ['caption' => __('site.for_directors.view.caption'), 'start' => __('site.for_directors.view.demo'), 'label' => $page['demo']['title'], 'private' => false])
                </div>
            </div>
        </section>

        {{-- An order, so numbered: each step's number on its part's glass gem. --}}
        <section class="s-wrap s-sec" aria-labelledby="steps-h">
            <h2 id="steps-h" class="s-title">{{ __('site.for_directors.steps_title') }}</h2>
            <ol class="s-dir-steps">
                @foreach ((array) \App\Support\Site\Teams::words('site.for_directors.steps') as [$heading, $body])
                    <li class="s-v{{ $loop->iteration }}"><span class="s-disc gem" aria-hidden="true">{{ $loop->iteration }}</span><h3>{{ $heading }}</h3><p>{{ $body }}</p></li>
                @endforeach
            </ol>
        </section>

        {{-- No group yet: the part pages, sent to each section. --}}
        <section class="s-wrap s-sec" aria-labelledby="nogroup-h">
            <div class="s-dir-nogroup">
                <div>
                    <h2 id="nogroup-h" class="s-title">{{ __('site.for_directors.nogroup.title') }}</h2>
                    <p class="s-lede">{{ __('site.for_directors.nogroup.body') }}</p>
                    <a href="/songs" class="s-arrow">{{ __('site.for_directors.nogroup.browse') }} <x-site.icon name="arrow" size="16" /></a>
                    {{-- In the carol season, the carols' page too (the carols are the songs most choirs are rehearsing). --}}
                    @include('site._carols-link', ['line' => __('site.carols.links.choir-directors')])
                </div>
                <ol class="s-dir-howto">
                    @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.for_directors.nogroup.steps') as $step)
                        <li class="s-v{{ $loop->iteration }}"><span class="s-disc gem" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- The courses that fit a choir (its part, sight-singing, diction, ear training), as every audience page links them. --}}
        @include('site._fit-courses')

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <div class="s-two">
                <h2 id="faq-h" class="s-title">{{ __('pages.pricing.faq_title') }}</h2>
                <div class="s-faq s-faq-round">
                    @foreach ($faq as [$question, $answer])
                        <details>
                            <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                            <p>{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
        <x-site.json-ld :data="[['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $faq)]]" />

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ __('site.for_directors.end.title') }}</h2>
                <p>{{ __('site.for_directors.end.body') }}</p>
                <p class="s-final-btns">
                    <a href="{{ $start }}" class="btn-primary btn-lg"><x-site.skin :game="__('site.game.co_op_start')" :serious="__('site.for_directors.start')" /> <x-site.icon name="arrow" size="18" /></a>
                    <a href="{{ $email }}" class="btn-secondary btn-lg">{{ __('site.for_directors.end.email') }}</a>
                </p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
