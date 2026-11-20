{{-- An AI part's credit and the call to record it (docs/ai-voices-build-plan.md, behind ai_voices): the app's own
     words (pages.song.ai, agreed with the frontend), "AI voice, waiting for a real singer", or the singer it was learned
     from, named, with permission; then "Real voice wanted: be the voice" where Be the voice is open (`wanted`, its
     address, else null). Shared by the song page, its part pages and About AI voices, so they never drift. --}}
<span data-ai-voice>{{ ($basedOn ?? null) !== null ? __('pages.song.ai.based_on', ['name' => $basedOn]) : __('pages.song.ai.credit') }}</span>
@if (($wanted ?? null) !== null)
    <a href="{{ $wanted }}" class="s-lk" @isset($part) data-be-voice="{{ $part }}" @endisset>{{ __('pages.song.ai.wanted') }}</a>
@endif
