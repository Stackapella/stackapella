{{-- /embed/h/<id> (contract/api.md, "/embed/h/<id>: public.embed-harmony (M9)"): the player alone,
     framed by other sites (Event Schedule's event pages): the title, who sang it, the waveform and
     the play button, and a link that opens the full page in a new tab. No Report, no OG tags. --}}
@extends('public.layout', ['title' => $harmony['title']])

@section('content')
    <main class="flex min-h-dvh flex-col justify-center gap-1.5 p-2">
        <div class="flex items-baseline justify-between gap-3">
            <p class="min-w-0 truncate text-sm"><span class="font-bold text-cream">{{ $harmony['title'] }}</span> <span class="text-muted">{{ $harmony['creditLine'] }}</span></p>
            <a href="{{ $harmony['pageUrl'] }}" target="_blank" rel="noopener" class="shrink-0 text-xs text-muted underline underline-offset-2 hover:text-cream">{{ __('meta.brand') }}</a>
        </div>
        <div class="rounded-2xl border border-line bg-panel p-2" data-player data-peaks="{{ json_encode($harmony['peaks']) }}" data-colors="{{ json_encode($harmony['colors']) }}" data-duration="{{ $harmony['durationSeconds'] }}">
            <div class="flex items-center gap-3">
                <button type="button" class="glass-bubble-solid hidden size-11 shrink-0 place-items-center rounded-full" data-play aria-label="{{ __('sharing.page.play') }}" data-label-play="{{ __('sharing.page.play') }}" data-label-pause="{{ __('sharing.page.pause') }}">
                    <svg data-icon-play width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg>
                    <svg data-icon-pause class="hidden" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z" fill="currentColor"/></svg>
                </button>
                <canvas class="hidden h-10 min-w-0 flex-1 cursor-pointer rounded-md focus-visible:outline-2 focus-visible:outline-cream" data-wave role="slider" tabindex="0" aria-label="{{ __('sharing.page.listen') }}" aria-valuemin="0"></canvas>
            </div>
            <p class="hidden text-end text-[11px] tabular-nums text-muted" data-time></p>
            <audio class="w-full" controls preload="metadata" data-audio>
                    <source src="{{ $harmony['audioUrl'] }}" type="{{ $harmony['audioType'] }}"></audio>
        </div>
    </main>
@endsection

@section('scripts')
    <script type="module" src="/js/public/harmony.js"></script>
@endsection
