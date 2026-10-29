{{-- A shared harmony at /h/<id> (contract/api.md, "Public pages: the view models (M6)"; the design
     round's direction): the title and who sang it, the player with one waveform row per part in its
     colour, "Learn this harmony" and "Make your own", three steps for someone new, the credits, and
     Report (it posts reports.create; js/public/harmony.js). It
     plays without the script too (the audio element's own controls). Unlisted: never indexed. --}}
@extends('public.layout', ['title' => $meta['title']])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:type" content="music.song">
    <meta property="og:site_name" content="{{ __('meta.brand') }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta property="og:image:width" content="{{ $meta['imageWidth'] }}">
    <meta property="og:image:height" content="{{ $meta['imageHeight'] }}">
    <meta property="og:image:alt" content="{{ $meta['imageAlt'] }}">
    <meta property="og:audio" content="{{ $meta['audio'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta['title'] }}">
    <meta name="twitter:description" content="{{ $meta['description'] }}">
    <meta name="twitter:image" content="{{ $meta['image'] }}">
    <meta name="twitter:image:alt" content="{{ $meta['imageAlt'] }}">
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-hero s-page s-harmony" aria-labelledby="title">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('sharing.page.eyebrow') }}</p>
            <h1 id="title" translate="no">{{ $harmony['title'] }}</h1>
            <div class="s-coach-top s-sung">
                <span class="s-faces" aria-hidden="true">
                    @foreach ($harmony['singers'] as $singer)
                        @if ($singer['avatarUrl'])
                            <img src="{{ $singer['avatarUrl'] }}" alt="" class="s-avatar" width="36" height="36">
                        @else
                            <span class="s-avatar s-avatar-initial s-v{{ min($loop->iteration, 4) }}">{{ mb_substr($singer['name'], 0, 1) }}</span>
                        @endif
                    @endforeach
                </span>
                <span><b translate="no">{{ $harmony['creditLine'] }}</b>
                    @if ($harmony['song'])
                        <span>{{ $harmony['part'] ? __('sharing.just_part', ['part' => $harmony['part'], 'song' => $harmony['song']['title']]) : __('sharing.song_line', ['song' => $harmony['song']['title']]) }}</span>
                    @endif
                </span>
            </div>

            {{-- The player (js/public/harmony.js): the round button, then one waveform row per part in
                 its colour (or the mix in the parts' colours); without the script, the audio's own controls. --}}
            <div class="s-mixer s-share-player" data-player data-peaks="{{ json_encode($harmony['peaks']) }}" data-colors="{{ json_encode($harmony['colors']) }}" data-parts="{{ json_encode($harmony['parts'] ?? []) }}" data-duration="{{ $harmony['durationSeconds'] }}">
                <div class="s-share-head">
                    <button type="button" class="s-play hidden" data-play aria-label="{{ __('sharing.page.play') }}" data-label-play="{{ __('sharing.page.play') }}" data-label-pause="{{ __('sharing.page.pause') }}">
                        <svg data-icon-play width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>
                        <svg data-icon-pause class="hidden" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z" fill="currentColor"/></svg>
                    </button>
                    <p class="s-t"><b>{{ count($harmony['parts'] ?? []) > 1 ? __('pages.harmony_page.together') : $harmony['title'] }}</b><span class="hidden s-share-time" data-time></span></p>
                </div>
                <div class="s-share-row">
                    @if (count($harmony['parts'] ?? []) > 1)
                        <span class="hidden s-share-parts" data-part-labels aria-hidden="true">
                            @foreach ($harmony['parts'] as $part)
                                <span class="s-disc" style="--c: var(--color-part-{{ ($part['index'] - 1) % 8 + 1 }}); --ct: var(--color-part-text-{{ ($part['index'] - 1) % 8 + 1 }})">{{ mb_substr($part['name'], 0, 1) }}</span>
                            @endforeach
                        </span>
                    @endif
                    <canvas class="hidden s-share-wave" data-wave role="slider" tabindex="0" aria-label="{{ __('sharing.page.listen') }}" aria-valuemin="0" @if (count($harmony['parts'] ?? []) > 1) style="height: {{ count($harmony['parts']) * 30 }}px" @endif></canvas>
                </div>
                <audio class="s-share-audio" controls preload="metadata" data-audio>
                    <source src="{{ $harmony['audioUrl'] }}" type="{{ $harmony['audioType'] }}">
                    <a href="{{ $harmony['audioUrl'] }}">{{ __('sharing.page.listen') }}</a>
                </audio>
            </div>
            <p class="s-share-btns s-harmony-btns">
                @if ($links['learn'])
                    <a href="{{ $links['learn'] }}" class="btn-primary btn-lg">{{ __('sharing.page.learn') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="{{ $links['app'] }}" class="{{ $links['learn'] ? 'btn-secondary' : 'btn-primary' }} btn-lg">{{ __('sharing.page.make') }}</a>
            </p>
        </section>

        {{-- For someone who's never heard of Stackapella: what it is, in three steps. --}}
        <section class="s-band s-harmony-band" aria-labelledby="how-h">
            <div class="s-wrap">
                <h2 id="how-h" class="s-h2">{{ __('pages.harmony_page.how_title') }}</h2>
                <p class="s-sub">{{ __('pages.harmony_page.how_lead') }}</p>
                <ol class="s-who s-how">
                    @foreach (['learn', 'record', 'stack'] as $step)
                        <li><span class="s-disc s-v{{ $loop->iteration }}" aria-hidden="true">{{ $loop->iteration }}</span><b>{{ __('pages.harmony_page.steps.'.$step.'.title') }}</b><span>{{ __('pages.harmony_page.steps.'.$step.'.body') }}</span></li>
                    @endforeach
                </ol>
                <p class="s-cur">{{ __('pages.site.start_note') }}</p>
            </div>
        </section>

        <section class="s-wrap s-share-credits" aria-label="{{ __('pages.song.credits') }}">
            @if ($harmony['song'] && $harmony['song']['credits'])
                <p>{{ $harmony['song']['credits'] }}</p>
            @endif
            @if (count($harmony['guides']))
                <p>{{ __('sharing.page.voices', ['names' => collect($harmony['guides'])->map(fn ($g) => $g['name'].' ('.$g['part'].')')->join(', ')]) }}</p>
            @endif
            <button type="button" class="hidden btn-ghost s-report" data-report-open>{{ __('sharing.page.report') }}</button>
        </section>

        {{-- Report (reports.create): signed out too. The honeypot stays empty. --}}
        <dialog class="m-auto w-[min(28rem,calc(100%-2rem))] rounded-3xl border border-line bg-panel p-6 text-cream backdrop:bg-black/60" data-report aria-labelledby="report-title">
            <form method="dialog" class="space-y-4" data-report-form data-harmony="{{ $report['harmonyId'] }}" data-api="{{ $links['api'] ?? '' }}">
                <h2 id="report-title" class="text-xl font-bold">{{ __('sharing.page.report_title') }}</h2>
                <fieldset class="space-y-2">
                    <legend class="mb-1 text-sm text-muted">{{ __('sharing.page.report_why') }}</legend>
                    @foreach (['copyright', 'offensive', 'spam', 'personal', 'other'] as $reason)
                        <label class="check-row text-sm"><input type="radio" name="reason" value="{{ $reason }}" class="radio" @checked($loop->first) required> <span>{{ __('sharing.page.reasons.'.$reason) }}</span></label>
                    @endforeach
                </fieldset>
                <label class="block text-sm text-muted">{{ __('sharing.page.report_note') }}
                    <textarea name="note" maxlength="500" rows="3" class="input mt-1"></textarea>
                </label>
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="hidden min-h-16" data-turnstile></div>
                <p class="hidden text-sm" role="status" data-report-status data-sent="{{ __('sharing.page.report_sent') }}" data-failed="{{ __('sharing.page.report_failed') }}"></p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-ghost btn-sm" data-report-close>{{ __('sharing.page.cancel') }}</button>
                    <button type="submit" class="btn-primary btn-sm" data-report-send>{{ __('sharing.page.report_send') }}</button>
                </div>
            </form>
        </dialog>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/harmony.js"></script>
@endsection
