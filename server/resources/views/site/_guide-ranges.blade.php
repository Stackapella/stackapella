{{-- The four choir parts' usual ranges, each to hear on "ah" from its lowest note to its highest
     (js/public/guide-ranges.js, on the audio clock), with a link to its voice type's page, then the guide's song's part pages while they're open
     (`parts`, its title `song`). The ranges are
     theory.js's VOICE_TYPES, so they match the voice type pages. Words: pages.guides.ranges. --}}
@php($names = ['C', 'C♯', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'A♭', 'A', 'B♭', 'B'])
@php($named = static fn (int $midi): string => $names[$midi % 12].(intdiv($midi, 12) - 1))
<section class="s-granges" aria-labelledby="ranges-h">
    <h3 id="ranges-h">{{ __('pages.guides.ranges.title') }}</h3>
    <p>{{ __('pages.guides.ranges.lead') }}</p>
    <ul class="s-granges-list">
        @foreach (['soprano' => [60, 81], 'alto' => [53, 74], 'tenor' => [48, 69], 'bass' => [40, 64]] as $part => [$low, $high])
            <li class="s-v{{ $loop->iteration }}">
                <button type="button" class="btn-secondary btn-sm" data-range-play data-low="{{ $low }}" data-high="{{ $high }}" data-play="{{ __('pages.guides.ranges.play') }}" data-stop="{{ __('pages.guides.ranges.stop') }}" aria-pressed="false" aria-describedby="range-{{ $part }}"><span class="s-granges-icon" aria-hidden="true"></span><span data-label>{{ __('pages.guides.ranges.play') }}</span></button>
                <span id="range-{{ $part }}"><b>{{ __('pages.guides.ranges.parts.'.$part) }}</b> <span translate="no">{{ __('pages.guides.ranges.range', ['low' => $named($low), 'high' => $named($high)]) }}</span></span>
                <a href="/voice-types/{{ $part }}" class="s-lk">{{ __('pages.guides.ranges.more', ['part' => strtolower(__('pages.guides.ranges.parts.'.$part))]) }}</a>
            </li>
        @endforeach
    </ul>
    @if ($parts !== [])
        <p class="s-granges-song">{{ __('pages.guides.song_parts', ['song' => $song]) }}:</p>
        <ul class="s-granges-parts">
            @foreach ($parts as $part)
                <li><a href="{{ $part['url'] }}" class="s-lk">{{ __('pages.guides.part_link', ['part' => $part['name']]) }}</a></li>
            @endforeach
        </ul>
    @endif
</section>
