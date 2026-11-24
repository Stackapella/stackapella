{{-- Make your own (the creator, built today): sing it, write it, or start from chords, beside the creator's Sing step as
     Hillel's landing redesign draws it (2026-11-15): Line 1 of 4, the line's words, its melody as you'd sing it, the other
     three parts the creator writes to fit under a dashed rule, and Sing line 1, in the app's own words. Never Hum a tune,
     which is still coming. --}}
<section id="own" class="s-wrap s-sec" aria-labelledby="own-h">
    <div class="s-split s-flip">
        <div>
            <p class="s-kick">{{ __('pages.landing.own.kick') }}</p>
            <h2 id="own-h" class="s-title">{{ __('pages.landing.own.title') }}</h2>
            <p class="s-lede">{{ __('pages.landing.own.body') }}</p>
            <ul class="s-ticks s-lr-ticks">
                @foreach (\App\Support\Pages\Landing::list('own.points') as $point)
                    <li><x-site.icon name="check" size="18" />{{ $point }}</li>
                @endforeach
            </ul>
            <p class="s-actions-l"><a href="{{ $links['create'] }}" class="btn-primary btn-lg">{{ __('pages.landing.make') }} <x-site.icon name="arrow" size="18" /></a></p>
        </div>
        @php($tuneParts = \App\Support\Site\Pictures::parts($hero ?? null))
        @if ($hero !== null && $tuneParts !== [])
            <div class="s-lr-tune" aria-hidden="true">
                <div class="s-lr-tune-card">
                    <p class="s-lr-tune-of">{{ \App\Support\Site\AppWords::fill('create.sing.lineOf', ['number' => 1, 'total' => 4]) }}</p>
                    <p class="s-lr-tune-line" translate="no">{{ \App\Support\Site\Pictures::firstWords($hero, 6) }}</p>
                    @php($melody = array_shift($tuneParts))
                    <div class="s-lr-tune-mel s-v{{ $melody['color'] }}">{{ \App\Support\Site\Pictures::roll($hero, $melody, 56) }}</div>
                    <div class="s-lr-tune-parts">
                        @foreach ($tuneParts as $part)
                            <div class="s-v{{ $part['color'] }}"><span translate="no">{{ $part['name'] }}</span>{{ \App\Support\Site\Pictures::roll($hero, $part, 20) }}</div>
                        @endforeach
                    </div>
                    <p class="s-lr-tune-go"><span><i></i>{{ \App\Support\Site\AppWords::fill('create.sing.singLine', ['number' => 1]) }}</span></p>
                </div>
            </div>
        @endif
    </div>
</section>
