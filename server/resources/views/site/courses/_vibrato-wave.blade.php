{{-- One of the Vibrato page's waves (App\Support\Pages\VibratoPage::wave): its shape, box, seconds, rate, width and scale
     as data for js/public/vibrato.js, which moves it while it's on screen, and its path at rest drawn here, so the page
     shows every wave before its script runs and under reduced motion. --}}
@php($w = \App\Support\Pages\VibratoPage::wave($spec))
<path class="{{ $class ?? 'cp-vi-line' }}" data-vi-wave="{{ $w['shape'] }}" data-w="{{ $w['w'] }}" data-h="{{ $w['h'] }}" data-x0="{{ $w['x0'] }}" data-x1="{{ $w['x1'] }}" data-secs="{{ $w['secs'] }}" data-rate="{{ $w['rate'] }}" data-cents="{{ $w['cents'] }}" data-scale="{{ $w['scale'] }}" data-from="{{ $w['from'] }}" data-to="{{ $w['to'] }}" data-tau="{{ $w['tau'] }}" data-speed="{{ $w['speed'] }}" data-n="{{ $w['n'] }}" @isset($own) data-vi-own @endisset d="{{ \App\Support\Pages\VibratoPage::path($spec) }}" />
