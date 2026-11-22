{{-- About AI voices (/about/ai-voices; docs/ai-voices-build-plan.md, marketing item 2): what an AI voice is, how it's
     labelled, that a real singer replaces it, whose voice it may be, that a singer's own voice never becomes one, and
     removal on request. Unlisted (no nav, footer or sitemap link); while the ai_voices switch is off, only an admin's
     device sees it, noindex (`indexed`). "Your voice stays yours" follows own_choir (`ownChoir`). The hero's card shows the label exactly as a song page's part row wears it (site._ai-voice). --}}
@extends('public.layout')

@section('arcade')@endsection

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
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <div class="s-split">
                <div>
                    <x-site.crumbs :trail="[[__('pages.nav.about'), '/about'], [__('site.ai_voices.crumb')]]" ld />
                    <h1 id="page-h">{{ __($choirOnly ? 'site.ai_voices.choir_only.headline' : 'site.ai_voices.headline') }}</h1>
                    <p class="s-lede">{{ __($choirOnly ? 'site.ai_voices.choir_only.lead' : 'site.ai_voices.lead') }}</p>
                </div>
                @unless ($choirOnly)
                <figure class="s-card3d" aria-labelledby="sample-h">
                    <p id="sample-h" class="s-kick">{{ __('site.ai_voices.sample.kick') }}</p>
                    <ul class="s-sg-how">
                        <li class="s-sg-how-row s-v2">
                            <div class="s-sg-how-facts">
                                <span class="s-disc" aria-hidden="true">{{ mb_substr(__('site.ai_voices.sample.part'), 0, 1) }}</span>
                                <h3>{{ __('site.ai_voices.sample.part') }}</h3>
                                <span class="s-sg-tag">{{ __('site.ai_voices.sample.role') }}</span>
                                <p class="s-sg-voice">@include('site._ai-voice', ['basedOn' => null, 'wanted' => '/songs'])</p>
                            </div>
                        </li>
                    </ul>
                    <figcaption class="s-sub">{{ __('site.ai_voices.sample.caption') }}</figcaption>
                </figure>
                @endunless
            </div>
        </section>

        <section class="s-wrap s-sec" aria-label="{{ __('site.ai_voices.title') }}">
            <div class="s-details">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.ai_voices.sections') as $key => $section)
                    @continue($choirOnly && in_array($key, ['checked', 'replace'], true))
                    @php($body = match (true) { $choirOnly && in_array($key, ['what', 'label', 'whose', 'yours'], true) => __('site.ai_voices.choir_only.'.$key), $key === 'yours' && $ownChoir => $section['choir'], default => $section['body'] })
                    <article id="{{ $key }}" class="s-v{{ $loop->index % 4 + 1 }}">
                        <h2><i class="gem" aria-hidden="true"></i>{{ $section['heading'] }}</h2>
                        <p>{{ $body }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="removal" class="s-wrap s-sec2" aria-labelledby="remove-h">
            <div class="s-two2">
                <div>
                    <p class="s-kick">{{ __('site.ai_voices.remove.kick') }}</p>
                    <h2 id="remove-h" class="s-h2">{{ __('site.ai_voices.remove.heading') }}</h2>
                </div>
                <p class="s-sub">{{ $remove }}</p>
            </div>
        </section>

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                @php($end = $choirOnly ? 'site.ai_voices.choir_only.end' : 'site.ai_voices.end')
                <h2 id="end-h">{{ __($end.'.title') }}</h2>
                <p>{{ __($end.'.body') }}</p>
                <p class="s-final-btns"><a href="{{ $choirOnly ? '/your-choir?ref=choir-about' : '/songs' }}" class="btn-primary btn-lg">{{ __($end.'.cta') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection
