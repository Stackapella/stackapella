{{-- Free and Plus side by side, one component for the landing and /pricing (Hillel's 2026-11-12 Pricing design; the
     five-pages plan: "one plan pair on home and pricing"). Both cards line up top to bottom: the name, who it's for, the
     price and its billing line, the action, a rule, then what you get. Plus is on a warm fill with a solid edge. While
     checkout is closed, Free's Start singing is the primary and Plus's place holds the Coming soon note; once it opens,
     Plus's Get Plus is the primary and Free's goes secondary. The Yearly or
     Monthly switch is the page's (the radios and :has, no script); each page prints its own lines under the cards (the
     tax, the choirs line). In the game skin the arcade dresses it. Button labels never wrap.
     $h: the heading level; $start and $plus: where the buttons go; $id: a prefix for the headings; $ai: false leaves the
     songs sung by AI out of both lists (the home's rule, and the arcade home's). --}}
@php($replace = [':free_songs' => (string) $plans['freeSongs'], ':free_storage' => $plans['freeStorage'], ':storage' => $plans['storage']])
@php($courses = app(\App\Support\Flags::class)->on('plus_courses'))
<div class="s-plans s-plans-2">
    <section class="s-plan" aria-labelledby="{{ $id }}-free">
        <{{ $h }} id="{{ $id }}-free" class="s-plan-h">{{ __('pages.landing.plans_free') }}</{{ $h }}>
        <p class="s-plan-for">{{ __('pages.landing.plans_free_for') }}</p>
        <p class="s-price">{{ str_replace('.00', '', \App\Support\Pages\Prices::format(0, $price['currency'])) }}</p>
        <p class="s-note">{{ __('pages.landing.plans_free_note') }}</p>
        <div class="s-plan-cta">
            <a href="{{ $start }}" class="{{ $plans['checkoutOpen'] ? 'btn-secondary' : 'btn-primary' }} btn-lg" data-funnel-start>{{ __('pages.landing.start') }}</a>
            <p class="s-note">{{ __('pages.pricing.free_note') }}</p>
        </div>
        <p class="s-plan-k">{{ __('pages.landing.plans_free_includes') }}</p>
        <ul>
            {{-- Every course is free, one line (Hillel, 2026-11-08); the beginner course alone until the courses open (plus_courses). --}}
            @foreach (\App\Support\Pages\Landing::list('plans_free_list', $replace, ai: $ai ?? true) as $line)
                <li><x-site.icon name="check" size="18" />{{ $loop->first && ! $courses ? __('pages.landing.plans_free_beginner') : $line }}</li>
            @endforeach
        </ul>
    </section>
    <section class="s-plan s-plan-plus" aria-labelledby="{{ $id }}-plus">
        <{{ $h }} id="{{ $id }}-plus" class="s-plan-h">{{ __('pages.landing.plans_plus') }} @unless ($plans['checkoutOpen'])<span class="s-plan-soon">{{ __('pages.landing.plans_soon') }}</span>@endunless</{{ $h }}>
        <p class="s-plan-for">{{ __('pages.landing.plans_plus_for') }}</p>
        <p class="s-price s-when-year">{{ $price['yearAsMonth'] }} <small>{{ __('pages.landing.plans_month') }}</small></p>
        <p class="s-note s-when-year">{{ __('pages.landing.plans_billed_year', ['year' => $price['year']]) }}</p>
        <p class="s-price s-when-month">{{ $price['month'] }} <small>{{ __('pages.landing.plans_month') }}</small></p>
        <p class="s-note s-when-month">{{ __('pages.landing.plans_billed_month') }}</p>
        {{-- No free trial (Hillel, 2026-11-21: "better to charge people from the start"): Get Plus. --}}
        <div class="s-plan-cta">
            @if ($plans['checkoutOpen'])
                <a href="{{ $plus }}" class="btn-primary btn-lg">{{ __('pages.landing.plans_get') }}</a>
                <p class="s-note">{{ __('pages.pricing.plus_note') }}</p>
            @else
                <p class="s-plan-paused">{{ __('pages.landing.plans_paused') }}</p>
            @endif
        </div>
        <p class="s-plan-k">{{ __('pages.landing.plans_plus_includes') }}</p>
        {{-- Each a benefit, then the plain fact (Hillel, 2026-11-08: "rework the plus features"). --}}
        <ul class="s-plan-gains">
            @foreach (\App\Support\Pages\Landing::list('plans_plus_list', ai: $ai ?? true) as [$gain, $fact])
                <li><x-site.icon name="check" size="18" /><span><strong>{{ $gain }}</strong> <span class="s-plan-fact">{{ strtr($fact, $replace) }}</span></span></li>
            @endforeach
        </ul>
    </section>
</div>
