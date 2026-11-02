{{-- One year on the Barbershop page's timeline (site/courses/barbershop): the year, what happened with
     its numbered notes, the margin notes that belong beside it, and, on the year the roots were
     owned (story.quote), the Society's own words set large. --}}
<li class="cp-bb2-row">
    <p class="cp-bb2-year">{{ $year }}</p>
    <p class="cp-bb2-entry">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
    @php($asides = array_filter((array) $love['story']['margins'], fn (array $m): bool => $m[0] === $year))
    @if ($asides !== [])
        <div class="cp-bb2-asides">
            @foreach ($asides as [, $aside, $asideCites])
                <p>{{ $aside }}@include('site.courses._cites', ['keys' => $asideCites])</p>
            @endforeach
        </div>
    @endif
    @if ($year === $love['story']['quote']['year'])
        <blockquote class="cp-bb2-pull"><p>“{{ $love['story']['quote']['text'] }}”</p></blockquote>
    @endif
</li>
