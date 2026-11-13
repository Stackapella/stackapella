{{-- A song's words (Hillel: one sung line a line; "what the words mean in English, or the user's language"): each stanza
     its lines, each line as it's sung, with its original script above it where the song has one (Hebrew right to left,
     Cyrillic) and its meaning in the page's language under it, smaller; every one wraps under itself (a hanging indent).
     `lines`: a list per stanza of {sung, script?, scriptLang?, meaning?} (SongPagesController; the plain sung lines
     until a song has its line by line meaning). A long song's meanings can be hidden (js/public/song-page.js); they show
     without the script. From the story's full words (SongStories::fullWords): `intro`, its note before the words (html
     with its numbered sources), and `labels`, each stanza's label or null. A long song shows its first three stanzas and
     folds the rest under Show all verses (a details element, no script). --}}
@php($fold = count($lines) > 4 ? 3 : count($lines))
<div class="s-sg-words-text" data-words>
    @if ($toggle)
        <button type="button" class="btn-secondary s-sg-meaning-toggle" data-meaning-toggle aria-expanded="true" aria-controls="song-words" data-label-show="{{ __('pages.song.meaning_show') }}" data-label-hide="{{ __('pages.song.meaning_hide') }}" hidden>{{ __('pages.song.meaning_hide') }}</button>
    @endif
    @if (($intro ?? '') !== '')
        <div class="s-prose s-sg-words-intro">{!! $intro !!}</div>
    @endif
    <div id="song-words" class="s-sg-lyrics">
        @foreach (array_slice($lines, 0, $fold) as $i => $stanza)
            @include('public._song-stanza', ['stanza' => $stanza, 'label' => ($labels ?? [])[$i] ?? null, 'sungLanguage' => $sungLanguage ?? null])
        @endforeach
        @if (count($lines) > $fold)
            <details class="s-sg-more">
                <summary class="btn-secondary">{{ __('pages.song.all_verses', ['count' => count($lines)]) }}</summary>
                <div class="s-sg-lyrics">
                    @foreach (array_slice($lines, $fold, null, true) as $i => $stanza)
                        @include('public._song-stanza', ['stanza' => $stanza, 'label' => ($labels ?? [])[$i] ?? null, 'sungLanguage' => $sungLanguage ?? null])
                    @endforeach
                </div>
            </details>
        @endif
    </div>
</div>
