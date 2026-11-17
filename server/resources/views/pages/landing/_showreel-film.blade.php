{{-- The film's frame (server/resources/promo/showreel/README.md), shared by the landing's "See it in action" band
     (_showreel) and the first screen of /how-it-works (Hillel, 2026-11-16: "find another page where we can use the show
     reel"): 16:9, the cut matching the look (game's, or calm's amber one: Hillel, 2026-11-17) and the theme, the
     language's own film. Driven by js/public/showreel.js. $reelPress: the
     poster with a play button and nothing fetched until it's pressed (as under reduced motion), where the frame is on the
     first screen. $reelSizes: the posters' sizes, the frame's width on the page. --}}
@php($reel = \App\Support\Site\Showreel::files())
<figure class="s-reel" data-reel @if ($reelPress ?? false) data-reel-press @endif data-play-label="{{ __('pages.landing.reel.play') }}" data-pause-label="{{ __('pages.landing.reel.pause') }}" data-sound-on="{{ __('pages.landing.reel.sound_on') }}" data-sound-off="{{ __('pages.landing.reel.sound_off') }}" data-captions-on="{{ __('pages.landing.reel.captions_on') }}" data-captions-off="{{ __('pages.landing.reel.captions_off') }}">
    @foreach (['dark', 'light', 'calm-dark', 'calm-light'] as $cut)
        <video class="s-reel-v" data-cut="{{ $cut }}" data-webm="{{ $reel[$cut]['webm'] }}" data-mp4="{{ $reel[$cut]['mp4'] }}" data-mobile="{{ $reel[$cut]['mobile'] }}" muted loop playsinline preload="none" aria-label="{{ __('pages.landing.reel.label') }}">
            <track kind="captions" srclang="{{ $reel['language'] }}" label="{{ __('pages.landing.reel.captions') }}" src="{{ $reel['captions'] }}">
        </video>
        <img class="s-reel-poster" data-cut="{{ $cut }}" src="{{ $reel[$cut]['poster'] }}" srcset="{{ $reel[$cut]['posterSet'] }}" sizes="{{ $reelSizes ?? '(min-width: 1180px) 1120px, 100vw' }}" width="1920" height="1080" alt="" loading="lazy" decoding="async">
    @endforeach
    <button type="button" class="s-reel-start glass-bubble-solid" data-reel-start aria-label="{{ __('pages.landing.reel.play') }}"><x-site.icon name="play" size="30" /></button>
    <button type="button" class="s-reel-btn s-reel-pause glass-bubble" data-reel-pause aria-label="{{ __('pages.landing.reel.pause') }}" hidden><span class="s-i-pause"><x-site.icon name="pause" size="20" /></span><span class="s-i-play"><x-site.icon name="play" size="20" /></span></button>
    <button type="button" class="s-reel-btn s-reel-cc glass-bubble" data-reel-cc aria-pressed="false" aria-label="{{ __('pages.landing.reel.captions_on') }}" hidden><span aria-hidden="true">{{ __('pages.landing.reel.cc') }}</span></button>
    <button type="button" class="s-reel-btn s-reel-sound glass-bubble" data-reel-sound aria-pressed="false" aria-label="{{ __('pages.landing.reel.sound_on') }}" hidden><span class="s-i-muted"><x-site.icon name="muted" size="20" /></span><span class="s-i-sound"><x-site.icon name="sound" size="20" /></span></button>
</figure>
{{-- The captions, under the film rather than on it, while CC is on (js/public/showreel.js writes the cue). --}}
<p class="s-reel-caption" data-reel-caption hidden></p>
<script type="module" src="/js/public/showreel.js"></script>
