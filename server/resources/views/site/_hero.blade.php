{{-- A site page's top: the four dots, its headline and lead. --}}
<section class="s-wrap s-hero s-page" aria-labelledby="page-h">
    <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ __('meta.brand') }}</p>
    <h1 id="page-h">{{ $headline }}</h1>
    <p class="s-lead">{{ $lead }}</p>
</section>
