{{-- "See it in action" (server/resources/promo/showreel/README.md): the film under the hero's mixer,
     16:9, the cut matching the theme. It loads nothing until it's in view and the page has been
     scrolled (preload none), plays muted and loops, and pauses when it leaves; pause and sound are
     glass bubbles in its bottom corners, with CC beside sound: captions only when asked for, remembered. Reduced motion or
     Save-Data: the poster and a play button. The frame itself is _showreel-film (/how-it-works shows it too). --}}
<section class="s-wrap s-reel-sec" aria-labelledby="reel-h">
    <p class="s-kick s-skin-g">{{ __('pages.landing.game.reel_kick') }}</p>
    <h2 id="reel-h" class="s-h2">{{ __('pages.landing.reel.title') }}</h2>
    @include('pages.landing._showreel-film')
</section>
