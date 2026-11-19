{{-- One of the Humming and lip trills page's sound shapes (App\Support\Site\HummingRibbons): the stroked line, the
     trill's bubbles and the "ah" widening, each only where the shape has it; coloured by the s-v class around it, sized by its kind. --}}
<g class="cp-hum-is-{{ $shape['kind'] }}">
@if ($shape['line'] !== '')
    <path d="{{ $shape['line'] }}" class="cp-hum-shape-line" />
@endif
@if ($shape['dots'] !== '')
    <path d="{{ $shape['dots'] }}" class="cp-hum-shape-dots" />
@endif
@if ($shape['fill'] !== '')
    <path d="{{ $shape['fill'] }}" class="cp-hum-shape-fill" />
@endif
</g>
