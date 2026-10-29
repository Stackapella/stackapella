{{-- /pricing: what each plan gets, as docs/features.md says, in the visitor's currency, from Hillel's
     Pricing design in the redesign's sections (docs/marketing-redesign-plan.md): the title with the
     Yearly or Monthly switch and the currency, Free and Plus (Hillel's plans design, Coming soon
     while checkout is paused), the comparison in groups, the questions, the courses coming to Plus on the
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
                    <h1 id="page-h">{{ __('pages.landing.plans_title') }} <span class="s-h-more">{{ __('pages.landing.plans_title_more') }}</span></h1>
                </div>
                <div class="s-pricehead-side">
                    <fieldset class="s-bill">
                        <legend class="sr-only">{{ __('pages.landing.plans_billing') }}</legend>
                        <label><input type="radio" name="billing" value="year" checked class="sr-only" /><span>{{ __('pages.landing.plans_yearly') }}</span>@if ($price['saving'] > 0)<b>{{ __('pages.landing.plans_save', ['percent' => $price['saving']]) }}</b>@endif</label>
                        <label><input type="radio" name="billing" value="month" class="sr-only" /><span>{{ __('pages.landing.plans_monthly') }}</span></label>
                    </fieldset>
                    <nav class="s-cur-seg" aria-label="{{ __('pages.pricing.currency_label') }}">
                        <span>{{ __('pages.pricing.currency_label') }}</span>
                        @foreach ($currencies as $currency)
                            @if ($currency === $price['currency'])
                                <strong aria-current="true">{{ __('pages.pricing.currencies.'.$currency) }}</strong>
                            @else
                                <a href="/pricing?currency={{ $currency }}">{{ __('pages.pricing.currencies.'.$currency) }}</a>
                            @endif
                        @endforeach
                    </nav>
                </div>
            </section>

            {{-- The two plans (Hillel's plans design, pages/_plan-cards). --}}
            <section class="s-wrap" aria-label="{{ __('pages.pricing.title') }}">
                @include('pages._plan-cards', ['h' => 'h2', 'id' => 'pricing', 'start' => \App\Support\Http\Hosts::app('/learn/1-comfortable'), 'plus' => \App\Support\Http\Hosts::app('/plus?from=pricing')])
                <p class="s-cur">{{ $price['taxIncluded'] ? __('pages.pricing.tax_included') : __('pages.pricing.tax_added') }} {{ $smallPrint[0] }}</p>
            </section>
        </div>

        {{-- Side by side, in groups, straight after the plans (Hillel: the courses go below it); a tick or a
             dash says yes or no (with the word for screen readers). --}}
        <section class="s-wrap s-sec2" aria-labelledby="compare-h">
            <h2 id="compare-h" class="s-title">{{ __('pages.pricing.compare') }}</h2>
            <table class="s-table s-table-fit s-table-sticky">
                <thead>
                    <tr>
                        <th scope="col">{{ __('pages.pricing.feature') }}</th>
                        <th scope="col">{{ __('pages.pricing.free') }}</th>
                        <th scope="col">{{ __('pages.pricing.plus') }}</th>
                    </tr>
                </thead>
                @foreach ($groups as $group => $keys)
                    <tbody>
                        <tr class="s-table-group"><th scope="colgroup" colspan="3">{{ __('pages.pricing.groups.'.$group) }}</th></tr>
                        @foreach ($keys as $key)
                            @continue(! isset($rows[$key]))
                            <tr>
                                <th scope="row">{{ $rows[$key][0] }}</th>
                                @foreach ([1, 2] as $plan)
                                    @php($cell = isset($limits[$key]) ? $limits[$key][$plan - 1] : $rows[$key][$plan])
                                    <td>
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
                        <details @if ($loop->first) open @endif>
                            <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                            {{-- The trial question (the second) has no trial to offer while checkout is paused. --}}
                            <p>{{ $loop->index === 1 && ! $checkoutOpen ? __('pages.pricing.faq_trial_paused') : $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Courses coming to Plus (docs/plus-courses-plan.md; Hillel: below the table, as three lists on a
             band): one Coming soon for the section, then each group's courses, numbered. --}}
        <section class="s-ink" aria-labelledby="courses-h" data-plus-courses>
            <div class="s-wrap">
                <div class="s-courses-head">
                    <div>
                        <p class="s-kick">{{ __('pages.pricing.courses_eyebrow') }} <x-site.soon /></p>
                        <h2 id="courses-h" class="s-title">{{ __('pages.pricing.courses_title') }}</h2>
                    </div>
                    <div class="s-courses-lead">
                        <p>{{ __('pages.pricing.courses_lead') }} <b>{{ __('pages.pricing.courses_free') }}</b></p>
                        <p>{{ __('pages.pricing.courses_coach') }}</p>
                    </div>
                </div>
                <div class="s-courses">
                    @foreach ((array) \Illuminate\Support\Facades\Lang::get('pages.pricing.courses') as [$group, $courses])
                        <section class="s-card3d s-v{{ $loop->iteration }}" aria-labelledby="courses-{{ $loop->iteration }}">
                            <header><h3 id="courses-{{ $loop->iteration }}"><i aria-hidden="true"></i>{{ $group }}</h3><span>{{ trans_choice('pages.pricing.courses_count', count($courses)) }}</span></header>
                            <ol>
                                @foreach ($courses as [$name, $line])
                                    <li><span aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><b>{{ $name }}</b><p>{{ $line }}</p></div></li>
                                @endforeach
                            </ol>
                        </section>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="s-wrap" aria-labelledby="end-h">
            <div class="s-end">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
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
