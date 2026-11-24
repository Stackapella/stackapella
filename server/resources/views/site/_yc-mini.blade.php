{{-- A scene's picture on /your-choir (site/your-choir.blade.php): its own lanes, the first bars of each (`beats` of them
     from the lane's first note), as the library draws a song: glass gems on a lane of the voice's colour, no words.
     `lanes`: the song's lanes (part, notes of beat, length, y), each in its voice's colour by where it stands; `keep`
     (optional) keeps only those voices; `chip` says what they are (AI, a real voice); `you` (optional: name, chip, notes)
     leads as the singer's own lane. A picture only, so hidden from a screen reader (the scene's words say it). --}}
@php($w = $beats ?? 9)
@php($rows = isset($you) ? [['n' => 1, 'name' => $you['name'], 'chip' => $you['chip'], 'notes' => $you['notes']]] : [])
@foreach ($lanes as $i => $lane)
    @if (! isset($keep) || in_array($lane['part'], $keep, true))
        @php($rows[] = ['n' => $i % 4 + 1, 'name' => __('pages.landing.hero.parts.'.$lane['part']), 'chip' => $chip, 'notes' => $lane['notes']])
    @endif
@endforeach
<div class="yc-mini" aria-hidden="true">
    @foreach ($rows as $row)
        @php($start = (float) (collect($row['notes'])->min('beat') ?? 0))
        <div class="yc-song yc-v{{ $row['n'] }}">
            <b><span class="yc-orb bubble bubble-sm">{{ mb_substr((string) $row['name'], 0, 1) }}</span>{{ $row['name'] }}@if (($row['chip'] ?? '') !== '')<span class="yc-chip">{{ $row['chip'] }}</span>@endif</b>
            <span class="yc-song-tr" style="--beats: {{ $w }}">
                @foreach ($row['notes'] as $note)
                    @if ((float) $note['beat'] - $start < $w)
                        <i class="gem-note" style="--b: {{ round((float) $note['beat'] - $start, 3) }}; --l: {{ round(min((float) $note['length'], $w - ((float) $note['beat'] - $start)), 3) }}; --y: {{ $note['y'] }}"></i>
                    @endif
                @endforeach
            </span>
        </div>
    @endforeach
</div>
