{{-- One stanza of a song's words (public._song-words): its label when the story's full words name it (Chorus, Verse 2),
     then each line as it's sung, its original script above it and its meaning under it. `sungLanguage` marks the sung
     line's language when it isn't a transliteration (screen readers and translation tools read it right). --}}
<p>
    @if (is_string($label ?? null) && $label !== '')
        <span class="s-sg-label">{{ $label }}</span>
    @endif
    @foreach ($stanza as $line)
        <span class="s-sg-line">
            @if (is_string($line['script'] ?? null) && $line['script'] !== '')
                <span class="s-sg-script" translate="no" @if (is_string($line['scriptLang'] ?? null)) lang="{{ $line['scriptLang'] }}" @endif dir="auto">{{ $line['script'] }}</span>
                <span class="s-sg-sung" translate="no">{{ $line['sung'] }}</span>
            @else
                <span class="s-sg-sung" translate="no"@if (is_string($sungLanguage ?? null)) lang="{{ $sungLanguage }}"@endif>{{ $line['sung'] }}</span>
            @endif
            @if (is_string($line['meaning'] ?? null) && $line['meaning'] !== '')
                <span class="s-sg-meaning">{{ $line['meaning'] }}</span>
            @endif
        </span>
    @endforeach
</p>
