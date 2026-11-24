{{-- A group of cards on a path page (site/for-path): each its name and one line, to its own page (a song, a guide, a
     tool, a feature), drawn as the site's cards are. `label` (or null) heads the group, `more` ([url, words] or null)
     leads to all of its kind. --}}
@if ($label !== null || $more !== null)
    <div class="s-headrow">
        @if ($label !== null)
            <h3 class="s-kick">{{ $label }}</h3>
        @endif
        @if ($more !== null)
            <a href="{{ $more[0] }}" class="s-arrow">{{ $more[1] }} <x-site.icon name="arrow" size="16" /></a>
        @endif
    </div>
@endif
<ul class="s-cards">
    @foreach ($cards as $card)
        <li>
            <a href="{{ $card['url'] }}" class="s-card">
                <b>{{ $card['name'] }}</b>
                @if ($card['soon'] ?? false)
                    <x-site.soon />
                @endif
                <span>{{ $card['summary'] }}</span>
            </a>
        </li>
    @endforeach
</ul>
