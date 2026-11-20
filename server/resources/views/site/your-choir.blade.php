{{-- Hear yourself as a choir (/your-choir; docs/ai-voices-build-plan.md, phase 3's launch page): ready, unpublished, and
     not found until the own_choir switch is on (an admin's device sees it first, noindex). Built on the angle: a real
     arrangement, any song from 30 seconds, learning your part, the ethics up front; AI said plainly, never first. The
     hero's try opens Silent Night in the app (`try`); then how it works in three steps, what makes it different (three),
     the ethics on a band of their own, the questions, and the close. No prices until Hillel sets them. --}}
@extends('public.layout')

@section('arcade')
@endsection

@if (! $indexed)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-featurehead s-abouthead" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('site.your_choir.eyebrow') }}</p>
            <h1 id="page-h">{{ __('site.your_choir.headline') }}</h1>
            <p class="s-lede">{{ __('site.your_choir.lead') }}</p>
            <div class="s-actions">
                <a href="{{ $try }}" class="btn-primary btn-lg" data-your-choir-try>{{ __('site.your_choir.start') }} <x-site.icon name="arrow" size="18" /></a>
                <p>{{ __('site.your_choir.start_note') }}</p>
            </div>
        </section>

        <section class="s-wrap s-sec s-maker" aria-labelledby="steps-h">
            <h2 id="steps-h" class="s-title">{{ __('site.your_choir.steps_title') }}</h2>
            <ol class="s-howsteps s-maker-steps">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.your_choir.steps') as $step)
                    <li class="s-v{{ $loop->iteration }}"><span class="s-disc" aria-hidden="true">{{ $loop->iteration }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $step['body'] }}</p></div></li>
                @endforeach
            </ol>
        </section>

        <section class="s-wrap s-sec" aria-label="{{ __('site.your_choir.points_title') }}">
            <div class="s-details">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.your_choir.points') as $key => $point)
                    <article id="{{ $key }}" class="s-v{{ $loop->index % 4 + 1 }}">
                        <h2><i class="gem" aria-hidden="true"></i>{{ $point['heading'] }}</h2>
                        <p>{{ $point['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- The ethics up front, on a band of their own. --}}
        <section id="honest" class="s-wrap s-sec2" aria-labelledby="honest-h">
            <div class="s-two2">
                <h2 id="honest-h" class="s-h2">{{ __('site.your_choir.honest.heading') }}</h2>
                <div>
                    <p class="s-sub">{{ __('site.your_choir.honest.body') }}</p>
                    <p class="s-more"><a href="/about/ai-voices" class="s-lk">{{ __('site.your_choir.more') }}</a></p>
                </div>
            </div>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <h2 id="faq-h" class="s-kick">{{ __('site.your_choir.faq_title') }}</h2>
            <div class="s-faq s-faq-round">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.your_choir.faq') as [$question, $answer])
                    <details>
                        <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ __('site.your_choir.end.title') }}</h2>
                <p>{{ __('site.your_choir.end.body') }}</p>
                <p class="s-final-btns"><a href="{{ $try }}" class="btn-primary btn-lg">{{ __('site.your_choir.start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
