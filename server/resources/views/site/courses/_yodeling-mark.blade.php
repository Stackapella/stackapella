{{-- The Yodeling page's section label as a trail marker (site/courses/yodeling): the yellow diamond of a Swiss hiking path,
     the section's name, and where the climb has got to (`love.alt.<at>`: a real place on the way to the Säntis, its height
     and its notes), or the marker and the name alone where the section has no place. A heading itself when given an id. --}}
@php($place = $love['alt'][$at] ?? null)
@php($tag = isset($id) ? 'h2' : 'p')
@if (($kick ?? '') !== '' || is_array($place))
<{{ $tag }} @isset($id) id="{{ $id }}" @endisset @class(['cp-yo3-mark', 'cp-yo3-mark-head' => ($tone ?? 'chest') === 'head'])>
    <span class="cp-yo3-diamond" aria-hidden="true"></span>
    @if (($kick ?? '') !== '')<span class="cp-yo3-kick">{{ $kick }}</span>@endif
    @if (is_array($place))<span class="cp-yo3-alt">{{ $place[0] }}@include('site.courses._cites', ['keys' => $place[1]])</span>@endif
</{{ $tag }}>
@endif
