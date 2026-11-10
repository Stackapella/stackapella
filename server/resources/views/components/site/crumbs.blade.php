{{-- The trail at the top of a page with a parent (Hillel, 2026-11-08: "show breadcrumbs at the top of pages so it's easy
     to navigate back (where it makes sense) ie. at the top of each tool"): each step a quiet link back up, the page
     itself last and not a link. `trail` is a list of [label, address], the last step [label] alone; `ld` also gives
     the same trail to search as BreadcrumbList data (StructuredData::breadcrumbs), for a page whose own data has none,
     so the two can't drift. A long trail wraps (input.css .s-crumb). --}}
@props(['trail', 'ld' => false])
<nav {{ $attributes->class(['s-crumb']) }} aria-label="{{ __('pages.site.crumb') }}">
    @foreach ($trail as $step)
        @if (! $loop->last)
            <a href="{{ $step[1] }}" @isset($step[2]) translate="no" @endisset>{{ $step[0] }}</a><span aria-hidden="true">/</span>
        @else
            <span aria-current="page" @isset($step[2]) translate="no" @endisset>{{ $step[0] }}</span>
        @endif
    @endforeach
</nav>
@if ($ld)
    <x-site.json-ld :data="[\App\Support\Site\StructuredData::breadcrumbs(array_map(static fn (array $step): array => [(string) $step[0], isset($step[1]) && is_string($step[1]) ? \App\Support\Http\Hosts::publicUrl().$step[1] : url()->current()], $trail))]" />
@endif
