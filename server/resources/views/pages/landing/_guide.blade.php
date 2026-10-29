{{-- Help fill in the recordings (Hillel): most songs start with a practice voice, and a public
     harmony's part may become the voice everyone learns from, credited (terms 4a). On the ink band
     (Hillel's sample 10), beside the app's Share card as it draws it, its words the app's own: the
     link, who can hear it, and featuring ticked (X and Bluesky: the share sheet and the terms both
     say so). --}}
<section id="guide-voices" class="s-ink" aria-labelledby="guide-h">
    <div class="s-wrap s-split">
        <div>
            <p class="s-kick">{{ __('pages.landing.guide.kick') }}</p>
            <h2 id="guide-h" class="s-title">{{ __('pages.landing.guide.title') }}</h2>
            <p class="s-lede">{{ __('pages.landing.guide.body') }}</p>
            <ul class="s-ticks">
                @foreach (\App\Support\Pages\Landing::list('guide.points') as $point)
                    <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                @endforeach
            </ul>
            <a href="/features/real-voices" class="s-arrow">{{ __('pages.landing.guide.more') }} <x-site.icon name="arrow" size="16" /></a>
        </div>
        <div class="s-card3d s-sharecard" aria-hidden="true">
            <header><b translate="no">{{ \App\Support\Site\AppWords::fill('share.titleOf', ['title' => $hero['title'] ?? __('meta.brand')]) }}</b><span>{{ __('pages.landing.hero.voices', ['count' => 4]) }}</span></header>
            <p class="s-linkrow"><span translate="no">{{ __('pages.landing.guide.link') }}</span><span class="btn-secondary btn-sm">{{ \App\Support\Site\AppWords::get('share.copy') }}</span></p>
            <p class="s-small">{{ \App\Support\Site\AppWords::get('share.anyone') }}</p>
            <p class="s-feature"><i><x-site.icon name="check" size="14" /></i><b>{{ \App\Support\Site\AppWords::get('share.feature') }}</b><span class="s-small">{{ \App\Support\Site\AppWords::get('share.featureHint') }}</span></p>
            <p class="s-small">{{ \App\Support\Site\AppWords::get('share.guides') }}</p>
        </div>
    </div>
</section>
