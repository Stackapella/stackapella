{{-- Free and Plus side by side (Hillel's plans design, designs/hillel/pricing-plans.png), for the landing
     and /pricing. Both cards line up top to bottom: the name, the price, the billing line, a rule,
     what you get, then the button at the foot. Plus is on a warm fill with a solid edge. Before
     checkout opens, Plus's foot is the Coming soon note at a button's height, so the cards still line
     up (no notify list: Hillel, "Keep Coming soon"); then it's Get Plus. The Yearly or Monthly switch
     is the page's (the radios and :has, no script). In the game skin the arcade dresses it.
     $h: the heading level; $start and $plus: where the buttons go; $id: a prefix for the headings. --}}
@php($replace = [':free_songs' => (string) $plans['freeSongs'], ':projects' => (string) $plans['projects'], ':storage' => $plans['storage']])
@php($courses = app(\App\Support\Flags::class)->on('plus_courses'))
<div class="s-plans s-plans-2">
    <section class="s-plan" aria-labelledby="{{ $id }}-free">
        <{{ $h }} id="{{ $id }}-free" class="s-plan-h">{{ __('pages.landing.plans_free') }}</{{ $h }}>
        <p class="s-price">{{ str_replace('.00', '', \App\Support\Pages\Prices::format(0, $price['currency'])) }}</p>
        <p class="s-note">{{ __('pages.landing.plans_free_note') }}</p>
        <p class="s-plan-k">{{ __('pages.landing.plans_free_includes') }}</p>
        <ul>
            @foreach (\App\Support\Pages\Landing::list('plans_free_list', $replace) as $line)
                <li><x-site.icon name="check" size="18" />{{ $line }}</li>
            @endforeach
        </ul>
        <div class="s-plan-foot">
            <a href="{{ $start }}" class="btn-primary btn-lg" data-funnel-start>{{ __('pages.landing.start') }}</a>
        </div>
    </section>
    <section class="s-plan s-plan-plus" aria-labelledby="{{ $id }}-plus">
        <{{ $h }} id="{{ $id }}-plus" class="s-plan-h">{{ __('pages.landing.plans_plus') }} @unless ($plans['checkoutOpen'])<span class="s-plan-soon">{{ __('pages.landing.plans_soon') }}</span>@endunless</{{ $h }}>
        <p class="s-price s-when-year">{{ $price['yearAsMonth'] }} <small>{{ __('pages.landing.plans_month') }}</small></p>
        <p class="s-note s-when-year">{{ __('pages.landing.plans_billed_year', ['year' => $price['year']]) }}</p>
        <p class="s-price s-when-month">{{ $price['month'] }} <small>{{ __('pages.landing.plans_month') }}</small></p>
        <p class="s-note s-when-month">{{ __('pages.landing.plans_billed_month') }}</p>
        <p class="s-plan-k">{{ __('pages.landing.plans_plus_includes') }}</p>
        <ul>
            @foreach (\App\Support\Pages\Landing::list('plans_plus_list', $replace) as $line)
                <li><x-site.icon name="check" size="18" />{{ $line }}</li>
                {{-- The Plus courses, once they're open (the plus_courses flag), after the full course. --}}
                @if ($loop->iteration === 2 && $courses)
                    <li><x-site.icon name="check" size="18" />{{ __('pages.landing.plans_plus_courses') }}</li>
                @endif
            @endforeach
        </ul>
        <div class="s-plan-foot">
            @if ($plans['checkoutOpen'])
                <a href="{{ $plus }}" class="btn-secondary btn-lg">{{ __('pages.landing.plans_get') }}</a>
                <p class="s-note">{{ __('pages.landing.plans_trial', ['days' => $plans['trialDays']]) }}</p>
            @else
                <p class="s-plan-paused">{{ __('pages.landing.plans_paused') }}</p>
            @endif
        </div>
    </section>
</div>
