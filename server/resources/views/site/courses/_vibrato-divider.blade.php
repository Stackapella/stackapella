{{-- A line between the Vibrato page's sections (VibratoPage::DIVIDERS): each wave wider down the page, drawn at 0.3 px a
     cent so ±50 is five times ±10, and labelled with its width and what that width is in the course. --}}
@php($cents = \App\Support\Pages\VibratoPage::DIVIDERS[$i] ?? 0)
<div class="cp-vi-div" aria-hidden="true">
    <svg viewBox="0 0 1440 48" preserveAspectRatio="none">@include('site.courses._vibrato-wave', ['spec' => ['shape' => 'even', 'w' => 1440, 'h' => 48, 'secs' => 5.33, 'rate' => 6, 'cents' => $cents, 'scale' => 0.3, 'speed' => 0.4, 'n' => 480], 'class' => 'cp-vi-div-line'])</svg>
    <span>{{ str_replace([':n', ':what'], [(string) $cents, (string) ($page['dividers'][$i] ?? '')], $page['divider']) }}</span>
</div>
