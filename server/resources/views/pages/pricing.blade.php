{{-- /pricing: what each plan gets, as docs/features.md says, in the visitor's currency, from Hillel's
     Pricing design in the redesign's sections (docs/marketing-redesign-plan.md): the title with the
     Yearly or Monthly switch and the currency, Free and Plus (Hillel's plans design, Coming soon
     while checkout is paused), the comparison in groups, the questions, the courses, all free, on the
     band below them, and the last word. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    @include('pages._head')
@endsection

@section('content')
    @include('pages._header')
    <main>
        {{-- The head and the plans share the Yearly or Monthly switch (no script: the radios and :has). --}}
        <div class="s-plans-sec">
            <section class="s-wrap s-pricehead" aria-labelledby="page-h">
                <div>
                    <p class="s-eyebrow s-hub-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span><x-site.skin :game="__('pages.landing.game.pricing_kick')" :serious="__('pages.pricing.eyebrow')" /></p>
                    {{-- The second line in the warm amber, the five pages' key phrase (never a fixed pink). --}}
                    <h1 id="page-h">{{ __('pages.landing.plans_title') }} <span class="s-h-more">{{ __('pages.landing.plans_title_more') }}</span></h1>
                    <p class="s-lede s-price-lede">{{ \App\Support\Site\Teams::text('pages.pricing.lede') }}</p>
                </div>
                {{-- How you pay and the currency, one row (the design), each a segmented control. --}}
                <div class="s-pricehead-side">
                    <fieldset class="s-bill">
                        <legend class="sr-only">{{ __('pages.landing.plans_billing') }}</legend>
                        <label><input type="radio" name="billing" value="year" checked class="sr-only" /><span>{{ __('pages.landing.plans_yearly') }}</span>@if ($price['saving'] > 0)<b>{{ __('pages.landing.plans_save', ['percent' => $price['saving']]) }}</b>@endif</label>
                        <label><input type="radio" name="billing" value="month" class="sr-only" /><span>{{ __('pages.landing.plans_monthly') }}</span></label>
                    </fieldset>
                    <nav class="s-cur-seg s-cur-sym" aria-label="{{ __('pages.pricing.currency_label') }}">
                        @foreach ($currencies as $currency)
                            @if ($currency === $price['currency'])
                                <strong aria-current="true"><span aria-hidden="true">{{ \App\Support\Pages\Prices::symbol($currency) }}</span><span class="sr-only">{{ __('pages.pricing.currencies.'.$currency) }}</span></strong>
                            @else
                                <a href="/pricing?currency={{ $currency }}" title="{{ __('pages.pricing.currencies.'.$currency) }}"><span aria-hidden="true">{{ \App\Support\Pages\Prices::symbol($currency) }}</span><span class="sr-only">{{ __('pages.pricing.currencies.'.$currency) }}</span></a>
                            @endif
                        @endforeach
                    </nav>
                </div>
            </section>

            {{-- The two plans (Hillel's plans design, pages/_plan-cards). --}}
            <section class="s-wrap" aria-label="{{ __('pages.pricing.title') }}">
                @include('pages._plan-cards', ['h' => 'h2', 'id' => 'pricing', 'start' => \App\Support\Http\Hosts::app('/learn/1-comfortable'), 'plus' => \App\Support\Http\Hosts::app('/plus?from=pricing')])
                {{-- Under the cards: the tax and renewal line at left, the choir offer at right (it isn't Plus). --}}
                <div class="s-plans-under">
                    <p class="s-cur">{{ $price['taxIncluded'] ? __('pages.pricing.tax_included') : __('pages.pricing.tax_added') }} {{ $smallPrint[0] }}</p>
                </div>
                {{-- Leading a choir? One callout (the approved blueprint, 2026-11-23): what it costs a choir, and the way to
                     how groups work. Its words follow the live switches (plus_teams, choir_seats). --}}
                <aside class="s-callout s-v4" aria-label="{{ __('pages.pricing.choir_link') }}">
                    <p>{{ \App\Support\Site\Teams::text('pages.pricing.choir_callout') }}</p>
                    {{-- While choir seats are on (Hillel, through the coordinator, 2026-11-26): what a seat costs, in the
                         visitor's currency from the billing config, that a group starts free, and what a seat covers. --}}
                    @if (\App\Support\Site\Teams::seats() && ($seat = \App\Support\Pages\Prices::seat($price['currency'])) !== null)
                        <p class="s-seat-price"><b>{{ __('pages.pricing.seat_price', ['price' => $seat]) }}</b> {{ __('pages.pricing.seat_free') }} {{ __('pages.pricing.faq_seat_choir') }}</p>
                    @endif
                    <a href="/for/choir-directors?ref=for-choirs-pricing" class="s-arrow-link">{{ __('pages.pricing.choir_link') }} <x-site.icon name="arrow" size="16" /></a>
                </aside>
            </section>
        </div>

        {{-- The difference, in short (Saul, the consultant: "radically shorter"; Hillel approved the blueprint, 2026-11-23):
             only the rows people decide on, then every detail one press away (a details element, no script): the full
             comparison in groups, with "Only what's different" and the small print. --}}
        <section class="s-wrap s-sec2 s-compare" aria-labelledby="compare-h">
            <h2 id="compare-h" class="s-title">{{ __('pages.pricing.compare_short') }}</h2>
            <table class="s-table s-table-fit s-table-plans s-table-short">
                <thead>
                    <tr>
                        <th scope="col"><span class="sr-only">{{ __('pages.pricing.feature') }}</span></th>
                        <th scope="col">{{ __('pages.pricing.free') }}</th>
                        <th scope="col" class="s-col-plus">{{ __('pages.pricing.plus') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($decide as $row)
                        @include('pages._compare-row', ['row' => $row])
                    @endforeach
                </tbody>
            </table>
            <details class="s-every">
                <summary>{{ __('pages.pricing.compare_all') }} <x-site.icon name="chevron" size="16" /></summary>
                <div class="s-compare-head">
                    <h3 class="sr-only">{{ __('pages.pricing.compare') }}</h3>
                    <label class="s-diff"><input type="checkbox" role="switch" class="sr-only" data-compare-diff /><span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span><span>{{ __('pages.pricing.only_different') }}</span></label>
                </div>
                <table class="s-table s-table-fit s-table-sticky s-table-plans">
                    <thead>
                        <tr>
                            <th scope="col"><span class="sr-only">{{ __('pages.pricing.feature') }}</span></th>
                            <th scope="col">{{ __('pages.pricing.free') }}</th>
                            <th scope="col" class="s-col-plus">{{ __('pages.pricing.plus') }}</th>
                        </tr>
                    </thead>
                    @foreach ($compare as $group)
                        <tbody @if ($group['same']) data-same @endif>
                            <tr class="s-table-group"><th scope="colgroup" colspan="2">{{ __('pages.pricing.groups.'.$group['key']) }}</th><td class="s-col-plus"></td></tr>
                            @foreach ($group['rows'] as $row)
                                @include('pages._compare-row', ['row' => $row])
                            @endforeach
                        </tbody>
                    @endforeach
                    {{-- The way to Plus at its column's foot, only once checkout is open. --}}
                    @if ($checkoutOpen)
                        <tfoot><tr><td colspan="2"></td><td class="s-col-plus"><a href="{{ \App\Support\Http\Hosts::app('/plus?from=pricing-table') }}">{{ __('pages.landing.plans_get') }}</a></td></tr></tfoot>
                    @else
                        <tfoot aria-hidden="true"><tr><td colspan="2"></td><td class="s-col-plus"></td></tr></tfoot>
                    @endif
                </table>
                <ul class="s-small">
                    @foreach (array_slice($smallPrint, 1) as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </details>
        </section>

        {{-- Words on this page: the four a singer may not know, each said plainly. --}}
        <section class="s-wrap s-sec2 s-gloss" aria-labelledby="gloss-h">
            <h2 id="gloss-h" class="s-title">{{ __('pages.pricing.glossary_title') }}</h2>
            <dl>
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('pages.pricing.glossary') as [$term, $meaning])
                    <div><dt>{{ $term }}</dt><dd>{{ $meaning }}</dd></div>
                @endforeach
            </dl>
        </section>

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <div class="s-two">
                <h2 id="faq-h" class="s-title">{{ __('pages.pricing.faq_title') }}</h2>
                <div class="s-faq s-faq-round">
                    @foreach ($faq as [$question, $short, $rest])
                        <details @if ($loop->first) open @endif>
                            <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                            {{-- The short answer first, in bold (the UX review, 2026-11-23). --}}
                            <p><b>{{ $short }}</b>@if ($rest !== '') {{ $rest }}@endif</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Every course, free: one short band (the approved blueprint; Saul: "radically shorter"), its lines and the
             way to them all on /courses, which lists each course and says which are still to come. The page ends in
             one dark block, the shared close, right under it. --}}
        <section class="s-pc-band" aria-labelledby="courses-h" data-plus-courses>
            <div class="s-wrap">
                <div class="s-courses-head">
                    <div>
                        <p class="s-kick">{{ __('pages.pricing.courses_eyebrow') }}</p>
                        <h2 id="courses-h" class="s-title">{{ __('pages.pricing.courses_title') }}</h2>
                    </div>
                    <div class="s-courses-lead">
                        <p>{{ __('pages.pricing.courses_lead') }} <b>{{ __('pages.pricing.courses_free') }}</b></p>
                        <p><a href="/courses" class="s-arrow-link">{{ __('pages.pricing.courses_browse') }} <x-site.icon name="arrow" size="16" /></a></p>
                    </div>
                </div>
            </div>
        </section>

        <x-site.close-band page="pricing" :href="\App\Support\Http\Hosts::app('/learn/1-comfortable')" />
    </main>
    @include('pages._footer')
@endsection
