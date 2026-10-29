{{-- "See it in action" (server/resources/promo/showreel/README.md): the film under the hero's mixer,
     16:9, the cut matching the theme. It loads nothing until it's in view and the page has been
     scrolled (preload none), plays muted and loops, and pauses when it leaves; pause and sound are
     glass bubbles in its bottom corners, the captions show with the sound. Reduced motion or
     Save-Data: the poster and a play button. Driven by js/public/showreel.js. --}}
@php($reel = \App\Support\Site\Showreel::files())
<section class="s-wrap s-reel-sec" aria-labelledby="reel-h">
    <p class="s-kick s-skin-g">{{ __('pages.landing.game.reel_kick') }}</p>
    <h2 id="reel-h" class="s-h2">{{ __('pages.landing.reel.title') }}</h2>
    <figure class="s-reel" data-reel data-play-label="{{ __('pages.landing.reel.play') }}" data-pause-label="{{ __('pages.landing.reel.pause') }}" data-sound-on="{{ __('pages.landing.reel.sound_on') }}" data-sound-off="{{ __('pages.landing.reel.sound_off') }}">
        @foreach (['dark', 'light'] as $cut)
            <video class="s-reel-v" data-cut="{{ $cut }}" data-webm="{{ $reel[$cut]['webm'] }}" data-mp4="{{ $reel[$cut]['mp4'] }}" data-mobile="{{ $reel[$cut]['mobile'] }}" muted loop playsinline preload="none" aria-label="{{ __('pages.landing.reel.label') }}">
                <track kind="captions" srclang="en" label="{{ __('pages.landing.reel.captions') }}" src="{{ $reel['captions'] }}">
            </video>
            <img class="s-reel-poster" data-cut="{{ $cut }}" src="{{ $reel[$cut]['poster'] }}" srcset="{{ $reel[$cut]['posterSet'] }}" sizes="(min-width: 1180px) 1120px, 100vw" width="1920" height="1080" alt="" loading="lazy" decoding="async">
        @endforeach
        <button type="button" class="s-reel-start glass-bubble-solid" data-reel-start aria-label="{{ __('pages.landing.reel.play') }}"><x-site.icon name="play" size="30" /></button>
        <button type="button" class="s-reel-btn s-reel-pause glass-bubble" data-reel-pause aria-label="{{ __('pages.landing.reel.pause') }}" hidden><span class="s-i-pause"><x-site.icon name="pause" size="20" /></span><span class="s-i-play"><x-site.icon name="play" size="20" /></span></button>
        <button type="button" class="s-reel-btn s-reel-sound glass-bubble" data-reel-sound aria-pressed="false" aria-label="{{ __('pages.landing.reel.sound_on') }}" hidden><span class="s-i-muted"><x-site.icon name="muted" size="20" /></span><span class="s-i-sound"><x-site.icon name="sound" size="20" /></span></button>
    </figure>
</section>
<script type="module" src="/js/public/showreel.js"></script>
