{{-- The choir gallery (/ai-harmony-singing/gallery and /es/armonias-con-ia/galeria; docs/choir-gallery-plan.md, 2; Hillel's
     design, 2026-11-26): the approved choirs, newest first, each one singer's own voice sung by AI into every part, shown
     as "A singer" (never a name) with its song (to its share page, the item's own page), its Play, progress and time, its
     parts, the AI label and Report. No counts: a showcase, not a contest. One plays at a time (js/public/choir-gallery.js;
     without a script each is its own player). Report posts reports.create with the item (js/public/harmony.js, signed out
     too); "This is my voice" holds it at once. Empty, it says the first choirs are on their way; Make your own choir is the
     hero's, and the grid's last tile. Words: site.choir_gallery. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="s-cg">
        {{-- The hero (Hillel's gallery design, 2026-11-26): the way back, the eyebrow, the headline and its line, and Make your
             own choir at its right (under it on a phone). --}}
        <section class="s-wrap s-featurehead s-cg-hero" aria-labelledby="page-h">
            <div>
                <x-site.crumbs :trail="[[__('site.your_choir.title'), $make], [__('site.choir_gallery.name')]]" ld />
                <p class="s-kick">{{ __('site.choir_gallery.eyebrow') }}</p>
                <h1 id="page-h">{{ __('site.choir_gallery.headline') }}</h1>
                <p class="s-lede">{{ __('site.choir_gallery.lead') }}</p>
            </div>
            <a href="{{ $make }}" class="btn-primary btn-lg s-cg-go">{{ __('site.choir_gallery.make') }} <x-site.icon name="arrow" size="18" /></a>
        </section>

        <section class="s-wrap s-sec s-cg-sec" aria-labelledby="page-h">
            @if ($items === [])
                <div class="s-cg-empty">
                    <p class="s-cg-empty-h">{{ __('site.choir_gallery.empty') }}</p>
                    <p>{{ __('site.choir_gallery.make_line') }}</p>
                </div>
            @else
                {{-- Three across, one on a phone. Each card: Play (named for its song) with the song and "A singer", its
                     progress as a slider with the time and its length, its parts, then the AI label and Report. A card never
                     changes size; one choir plays at a time (js/public/choir-gallery.js). Without a script, each is its own
                     <audio> player. The last tile is the way to make your own. --}}
                <ul class="s-cg-list">
                    @foreach ($items as $item)
                        <li class="s-cg-item" data-gallery-item>
                            <div class="s-cg-head">
                                <button type="button" class="hidden s-cg-disc" data-gallery-play aria-pressed="false" aria-label="{{ __('site.choir_gallery.play', ['song' => $item['title']]) }}" data-label-play="{{ __('site.choir_gallery.play', ['song' => $item['title']]) }}" data-label-stop="{{ __('site.choir_gallery.stop') }}"><x-site.icon name="play" size="20" /><x-site.icon name="stop" size="20" /></button>
                                <div class="s-cg-name">
                                    <h2 class="s-cg-title" translate="no"><a href="/h/{{ $item['harmonyId'] }}">{{ $item['title'] }}</a></h2>
                                    <p class="s-cg-singer">{{ __('site.choir_gallery.singer') }}</p>
                                </div>
                            </div>
                            <div class="hidden s-cg-track" data-gallery-track>
                                <div class="s-cg-bar" role="slider" tabindex="0" aria-label="{{ $item['title'] }}" aria-valuemin="0" aria-valuemax="{{ $item['seconds'] }}" aria-valuenow="0" aria-valuetext="0:00 / {{ $item['length'] }}" data-gallery-seek><i data-gallery-fill></i></div>
                                <p class="s-cg-times" aria-hidden="true"><span data-gallery-now>0:00</span><span>{{ $item['length'] }}</span></p>
                            </div>
                            <audio class="s-cg-audio" controls preload="none" data-gallery-audio data-seconds="{{ $item['seconds'] }}">
                                <source src="{{ $item['audioUrl'] }}" type="{{ $item['audioType'] }}">
                            </audio>
                            @if ($item['parts'] !== [])
                                <ul class="s-cg-parts">
                                    @foreach ($item['parts'] as $part)
                                        <li>{{ $part }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="s-cg-foot">
                                <p class="s-cg-label"><span class="s-cg-ai" aria-hidden="true">{{ __('site.your_choir.stage.ai_chip') }}</span>{{ __('site.choir_gallery.label') }}</p>
                                <button type="button" class="hidden s-cg-report" data-report-open data-gallery="{{ $item['id'] }}">{{ __('site.choir_gallery.report.open') }}</button>
                            </div>
                        </li>
                    @endforeach
                    <li class="s-cg-tile">
                        <a href="{{ $make }}"><b>{{ __('site.choir_gallery.make') }} <x-site.icon name="arrow" size="20" /></b><span>{{ __('site.your_choir.title') }}</span></a>
                    </li>
                </ul>
            @endif
        </section>

        {{-- Report (reports.create, with the item): signed out too. The honeypot stays empty. "It isn't the singer's own
             voice" is not_own and "This is my voice" my_voice, the gallery's own reasons (contract/api.md); there's no email
             field until the API has one (the plan's version 2). --}}
        @if ($items !== [])
            <dialog class="m-auto w-[min(28rem,calc(100%-2rem))] rounded-3xl border border-line bg-panel p-6 text-cream backdrop:bg-black/60" data-report aria-labelledby="report-title">
                <form method="dialog" class="space-y-4" data-report-form data-harmony="" data-api="{{ $links['api'] ?? '' }}">
                    <h2 id="report-title" class="text-xl font-bold">{{ __('site.choir_gallery.report.title') }}</h2>
                    <fieldset class="space-y-2">
                        <legend class="sr-only">{{ __('site.choir_gallery.report.title') }}</legend>
                        {{-- Marketing's order; the first is chosen, never "This is my voice", which holds the choir at once. --}}
                        @foreach (['not_theirs' => 'not_own', 'mine' => 'my_voice', 'offensive' => 'offensive', 'copyright' => 'copyright', 'other' => 'other'] as $key => $value)
                            <label class="check-row text-sm"><input type="radio" name="reason" value="{{ $value }}" class="radio" @checked($loop->first) required> <span>{{ __('site.choir_gallery.report.reasons.'.$key) }}</span></label>
                        @endforeach
                    </fieldset>
                    <label class="block text-sm text-muted">{{ __('sharing.page.report_note') }}
                        <textarea name="note" maxlength="500" rows="3" class="input mt-1"></textarea>
                    </label>
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="hidden min-h-16" data-turnstile></div>
                    <p class="hidden text-sm" role="status" data-report-status data-sent="{{ __('site.choir_gallery.report.done') }}" data-failed="{{ __('sharing.page.report_failed') }}"></p>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-ghost btn-sm" data-report-close>{{ __('sharing.page.cancel') }}</button>
                        <button type="submit" class="btn-primary btn-sm" data-report-send>{{ __('site.choir_gallery.report.send') }}</button>
                    </div>
                </form>
            </dialog>
        @endif
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    @if ($items !== [])
        <script type="module" src="/js/public/choir-gallery.js"></script>
        <script type="module" src="/js/public/harmony.js"></script>
    @endif
@endsection
