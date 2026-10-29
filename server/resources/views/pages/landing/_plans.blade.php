{{-- Free and Plus (docs/features.md; Hillel's plans design), with the prices in the visitor's currency
     and the numbers from config: the title with its second line muted, the Yearly or Monthly switch
     level with its foot, and the two cards (pages/_plan-cards). --}}
<section id="plans" class="s-wrap s-sec s-plans-sec" aria-labelledby="plans-h">
    <div class="s-plans-head">
        <div>
            <p class="s-kick s-skin-g">{{ __('pages.landing.game.plans_kick') }}</p>
            <h2 id="plans-h" class="s-h2">{{ __('pages.landing.plans_title') }}<br><span class="s-h-more">{{ __('pages.landing.plans_title_more') }}</span></h2>
        </div>
        {{-- Yearly or monthly: which Plus price shows (no script; the radios and :has). --}}
        <fieldset class="s-bill">
            <legend class="sr-only">{{ __('pages.landing.plans_billing') }}</legend>
            <label><input type="radio" name="billing" value="year" checked class="sr-only" /><span>{{ __('pages.landing.plans_yearly') }}</span>@if ($price['saving'] > 0)<b>{{ __('pages.landing.plans_save', ['percent' => $price['saving']]) }}</b>@endif</label>
            <label><input type="radio" name="billing" value="month" class="sr-only" /><span>{{ __('pages.landing.plans_monthly') }}</span></label>
        </fieldset>
    </div>
    @include('pages._plan-cards', ['h' => 'h3', 'id' => 'plans', 'start' => $links['start'], 'plus' => $links['plus']])
    <div class="s-plans-foot">
        <p class="s-cur">{{ $price['taxIncluded'] ? __('pages.pricing.tax_included') : __('pages.pricing.tax_added') }}</p>
        <a href="/pricing" class="btn-ghost">{{ __('pages.landing.plans_compare') }} <x-site.icon name="arrow" size="16" /></a>
    </div>
</section>
