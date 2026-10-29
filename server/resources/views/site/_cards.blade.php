{{-- Feature cards: each its name and one line, with the quiet label while it's coming. --}}
<ul class="s-cards">
    @foreach ($cards as $card)
        <li>
            <a href="{{ $card['url'] }}" class="s-card">
                <b>{{ $card['name'] }}</b>
                @if ($card['soon'])
                    <x-site.soon />
                @endif
                <span>{{ $card['summary'] }}</span>
            </a>
        </li>
    @endforeach
</ul>
