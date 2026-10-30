{{-- A course's "Did you know?" facts, as its lessons tell them (the courses lead's checked facts),
     each with its source, linked where it has one. --}}
<ul class="cp-facts">
    @foreach ((array) $page['facts'] as [$text, $source, $url])
        <li class="cp-fact">
            <p>{{ $text }}</p>
            <p class="cp-fact-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
        </li>
    @endforeach
</ul>
