{{-- Who sang which part: each name, linked to their public profile when they have one. --}}
<ul class="s-credits">
    @foreach ($credits as $credit)
        @php($part = collect($parts)->firstWhere('key', $credit['partKey']))
        <li data-part="{{ $credit['partKey'] }}">
            @include('public._avatar', ['coach' => $credit['singer'], 'size' => 32])
            <span>
                @if ($credit['singer']['handle'] ?? null)
                    <a href="{{ \App\Support\Http\Hosts::public('/@'.$credit['singer']['handle']) }}" class="link-quiet" translate="no">{{ $credit['singer']['name'] }}</a>
                @else
                    <b translate="no">{{ $credit['singer']['name'] }}</b>
                @endif
                <span>{{ $part['name'] ?? $credit['partKey'] }}</span>
            </span>
        </li>
    @endforeach
</ul>
