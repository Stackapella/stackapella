{{-- Make your own (the creator, built today): sing it, write it, or start from chords, beside the
     creator's Sing step. Never Hum a tune, which is still coming. --}}
<section id="own" class="s-wrap s-sec" aria-labelledby="own-h">
    <div class="s-split s-flip">
        <div>
            <p class="s-kick">{{ __('pages.landing.own.kick') }}</p>
            <h2 id="own-h" class="s-title">{{ __('pages.landing.own.title') }}</h2>
            <p class="s-lede">{{ __('pages.landing.own.body') }}</p>
            <ul class="s-ticks">
                @foreach (\App\Support\Pages\Landing::list('own.points') as $point)
                    <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                @endforeach
            </ul>
            <p class="s-actions-l"><a href="{{ $links['create'] }}" class="btn-primary btn-lg">{{ __('pages.landing.make') }} <x-site.icon name="arrow" size="18" /></a></p>
        </div>
        @if ($hero !== null)
            <div class="s-card3d"><x-site.pic name="singline" :hero="$hero" :pics="$pictures ?? []" /></div>
        @endif
    </div>
</section>
