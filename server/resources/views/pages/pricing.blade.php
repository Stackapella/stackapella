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
                    <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span><x-site.skin :game="__('pages.landing.game.pricing_kick')" :serious="__('pages.pricing.title')" /></p>
                    {{-- Hillel's 2026-11-12 design: the plan's name in its card's warm ink (never a fixed pink). --}}
                    <h1 id="page-h">{{ __('pages.landing.plans_title') }} <span class="s-h-more">{{ $titleMore[0] }}@if ($titleMore[1] !== '')<span class="s-h-plus">{{ $titleMore[1] }}</span>@endif{{ $titleMore[2] }}</span></h1>
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
                @include('pages._plan-cards', ['h' => 'h2', 'id' => 'pricing', 'lead' => true, 'start' => \App\Support\Http\Hosts::app('/learn/1-comfortable'), 'plus' => \App\Support\Http\Hosts::app('/plus?from=pricing')])
                {{-- Under the cards: the tax and renewal line at left, the choir offer at right (it isn't Plus). --}}
                <div class="s-plans-under">
                    <p class="s-cur">{{ $price['taxIncluded'] ? __('pages.pricing.tax_included') : __('pages.pricing.tax_added') }} {{ $smallPrint[0] }}</p>
                    <p class="s-plan-choirs">{{ __('pages.landing.plans_choirs') }} <a href="/for/choir-directors?ref=for-choirs-pricing">{{ \App\Support\Site\Teams::text('pages.landing.plans_choirs_link') }}</a></p>
                </div>
            </section>
        </div>

        {{-- Side by side, in groups, straight after the plans (Hillel: the courses go below it); a tick or a
             dash says yes or no (with the word for screen readers). Plus's column on the card's warm fill, and
             "Only what's different" (a checkbox and :has, no script) folds every row both plans share. --}}
        <section class="s-wrap s-sec2 s-compare" aria-labelledby="compare-h">
            <div class="s-compare-head">
                <h2 id="compare-h" class="s-title">{{ __('pages.pricing.compare') }}</h2>
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
                            <tr @if ($row['same']) data-same @endif>
                                <th scope="row">{{ $row['label'] }}</th>
                                @foreach ($row['cells'] as $i => $cell)
                                    <td @class(['s-col-plus' => $i === 1, 's-plusmore' => $i === 1 && ! $row['same'] && $cell !== __('pages.pricing.yes')])>
                                        @if ($cell === __('pages.pricing.yes'))
                                            <span class="s-yes"><x-site.icon name="check" size="18" /><span class="sr-only">{{ $cell }}</span></span>
                                        @elseif ($cell === __('pages.pricing.no'))
                                            <span class="s-no" aria-hidden="true"></span><span class="sr-only">{{ $cell }}</span>
                                        @else
                                            {{ $cell }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
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
        </section>

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <div class="s-two">
                <h2 id="faq-h" class="s-title">{{ __('pages.pricing.faq_title') }}</h2>
                <div class="s-faq s-faq-round">
                    @foreach ($faq as [$question, $answer])
                        {{-- With checkout open and no trial days, there's no trial to ask about. --}}
                        @continue($loop->index === 1 && $checkoutOpen && $trialDays === 0)
                        <details @if ($loop->first) open @endif>
                            <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                            {{-- The trial question (the second) has no trial to offer while checkout is paused. --}}
                            <p>{{ $loop->index === 1 && ! $checkoutOpen ? __('pages.pricing.faq_trial_paused') : $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- The courses, all free (Hillel, 2026-11-06; docs/plus-courses-plan.md; Hillel: below the table, as three lists on a band), as the
             app's own More courses list has them (ce5498af): each group's open courses as short rows, linked to
             their pages, then the ones still to come in one quiet line. Open is the catalogue's own (live, with
             plus_courses on), so the page is never ahead of the app. --}}
        <section class="s-ink" aria-labelledby="courses-h" data-plus-courses>
            <div class="s-wrap">
                <div class="s-courses-head">
                    <div>
                        @php($anyOpen = collect(\App\Support\Site\SitePages::COURSES)->contains(fn (string $slug): bool => \App\Support\Site\SitePages::courseOpen($slug)))
                        <p class="s-kick">{{ __('pages.pricing.courses_eyebrow') }}@unless ($anyOpen) <x-site.soon />@endunless</p>
                        <h2 id="courses-h" class="s-title">{{ __('pages.pricing.courses_title') }}</h2>
                    </div>
                    <div class="s-courses-lead">
                        <p>{{ __('pages.pricing.courses_lead') }} <b>{{ __('pages.pricing.courses_free') }}</b></p>
                        <p>{{ __('pages.pricing.courses_coach') }}</p>
                    </div>
                </div>
                <div class="s-courses">
                    {{-- The groups as /courses has them (the catalogue's, SitePages::courseGroups), each course's name and line from its page. --}}
                    @foreach (\App\Support\Site\SitePages::courseGroups() as $group)
                        <section class="s-card3d s-v{{ $loop->iteration }}" aria-labelledby="courses-{{ $loop->iteration }}">
                            <header><h3 id="courses-{{ $loop->iteration }}"><i aria-hidden="true"></i>{{ $group['name'] }}</h3><span>{{ trans_choice('pages.pricing.courses_count', count($group['courses'])) }}</span></header>
                            @php($split = \App\Support\Site\SitePages::courseSplit($group['courses']))
                            {{-- Each open course a chip to its page (Hillel's 2026-11-12 design), then the ones still to come as one quiet chip. --}}
                            <div class="s-coursechips">
                                @if ($split['open'])
                                    <ul data-courses-open>
                                        @foreach ($split['open'] as $slug)
                                            <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                                {{-- Before any course opens, the eyebrow already says Coming soon: the chip is just the names. --}}
                                @if ($split['soon'] !== '')
                                    <p class="s-coursechip-soon" data-courses-soon>@if ($anyOpen)<b>{{ __('pages.nav.coming_soon') }}</b> @endif{{ $split['soon'] }}</p>
                                @endif
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="s-wrap" aria-labelledby="end-h">
            {{-- The last word, plain (the design: no art beside it). --}}
            <div class="s-end s-end-plain">
                <div>
                    <h2 id="end-h">{{ __('pages.site.final_title') }}</h2>
                    <p>{{ __('pages.site.start_note') }}</p>
                </div>
                <a href="{{ \App\Support\Http\Hosts::app('/learn/1-comfortable') }}" class="btn-primary btn-lg">{{ __('pages.pricing.start_free') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    </main>
    @include('pages._footer')
@endsection
