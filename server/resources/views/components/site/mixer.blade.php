{{-- A song's line in four voices, each one switchable, played in the app's guide voices by
     js/public/mixer.js (board-a2's mixer, each voice's row its own notes as bars, Hillel). `song` is
     Landing::hero()'s shape. The words and the
     text alternative are here, so the page says what it plays without the script. The slot is what
     sits beside Play on desktop (the hero's Start singing). `on`, when given, lists the part keys
     switched on at first (A cappella's stack starts with the bass alone); the rest start off. `foot`, a
     named slot, is the card's last row (the landing's facts; a song page's Learn and Share). `picked` false starts
     with no part yours (a song page: nothing looks picked until it is). A part's `line`, when given, is its row's small
     line (a song page's "Melody"), with its `rangeLine` after it ("· B3 to E5", one unbroken piece), else its role. --}}
@props(['song', 'id' => 'mixer', 'studio' => false, 'on' => null, 'picked' => true])
<div @class(['s-mixer', 's-mixer-studio' => $studio]) id="{{ $id }}" data-player role="group" aria-labelledby="{{ $id }}-title">
    <script type="application/json" data-song>@json($song)</script>
    <div class="s-mix-head">
        <button type="button" class="s-play" data-play aria-label="{{ __('pages.landing.hero.play') }}" data-label-play="{{ __('pages.landing.hero.play') }}" data-label-stop="{{ __('pages.landing.hero.stop') }}">
            <span class="s-i-play"><x-site.icon name="play" size="26" /></span><span class="s-i-stop"><x-site.icon name="stop" size="26" /></span>
        </button>
        <p class="s-t"><b id="{{ $id }}-title">{{ __(($song['whole'] ?? false) ? 'pages.landing.hero.title_whole' : 'pages.landing.hero.title', ['song' => $song['title']]) }}</b><span class="s-mix-meta">{{ __('pages.landing.hero.voices', ['count' => count($song['parts'])]) }} · <span data-time>0:00 / {{ $song['length'] }}</span></span><span class="s-mix-loading" data-loading-line hidden>{{ __('pages.landing.hero.loading') }}</span></p>
        {{ $slot }}
    </div>
    {{-- Each voice is a row you tap to hear it alone (tap it again for all of them), with a small My part at its end
         that makes it yours (the louder one, "You"). The Studio's rows keep their switch, solo, level and pan. Its disc is
         glass (the app's .gem, in the part's colour), dimmed while the voice is off. --}}
    @php($yourPart = collect($song['parts'])->firstWhere('key', $song['you'])['name'] ?? '')
    @unless ($studio)
        <p class="s-mix-lead">{{ __('pages.landing.hero.hear_lead') }}</p>
    @endunless
    <div class="s-channels" role="radiogroup" aria-label="{{ __('pages.landing.hero.parts_label') }}">
        @foreach ($song['parts'] as $part)
            @php($you = $picked && $part['key'] === $song['you'])
            @php($isOn = $on === null || in_array($part['key'], $on, true))
            <div class="s-ch s-v{{ $part['color'] }} {{ $you ? 's-is-you' : '' }}" data-voice="{{ $part['key'] }}" @if (! $isOn) data-off @endif>
                @if ($studio)
                    <button type="button" class="s-ch-pick" role="radio" aria-checked="{{ $you ? 'true' : 'false' }}" tabindex="{{ $you ? '0' : '-1' }}" data-pick aria-label="{{ $you ? __('pages.landing.hero.your_part', ['part' => $part['name']]) : $part['name'] }}" data-label="{{ $part['name'] }}" data-label-you="{{ __('pages.landing.hero.your_part', ['part' => $part['name']]) }}"></button>
                @else
                    {{-- The whole row: hear this part alone (a singer, then the fresh-eyes check, 2026-11-13: one control, the row itself); again, all of them. --}}
                    <button type="button" class="s-ch-pick" aria-pressed="false" data-hear-alone aria-label="{{ __('pages.landing.hero.hear_only', ['part' => $part['name']]) }}" data-label="{{ __('pages.landing.hero.hear_only', ['part' => $part['name']]) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}"></button>
                @endif
                <span @class(['s-disc', 'gem', 'gem-dim' => ! $isOn]) aria-hidden="true">{{ mb_substr($part['name'], 0, 1) }}</span>
                <span class="s-ch-name" aria-hidden="true"><b>@unless ($studio)<span class="s-ch-play"><x-site.icon name="play" size="12" /></span>@endunless{{ $part['name'] }}</b>
                    <span class="s-you">{{ __('pages.landing.hero.you') }}</span>
                    @if (is_string($part['line'] ?? null))
                        <small>{{ $part['line'] }}@if (is_string($part['rangeLine'] ?? null)) <span class="s-ch-range" translate="no">· {{ $part['rangeLine'] }}</span>@endif</small>
                    @elseif (\Illuminate\Support\Facades\Lang::has('pages.landing.hero.roles.'.$part['key']))
                        <small>{{ __('pages.landing.hero.roles.'.$part['key']) }}</small>
                    @endif
                </span>
                <span class="s-ch-wave" aria-hidden="true">{{ \App\Support\Site\Drawings::roll($part['notes'], $part['range'], (float) $song['beats']) }}</span>
                @if ($studio)
                    {{-- The studio's controls (the Studio feature page): solo, level and pan, read by js/public/mixer.js. --}}
                    <span class="s-ch-studio">
                        <button type="button" class="s-ch-solo" data-solo="{{ $part['key'] }}" aria-pressed="false" aria-label="{{ __('pages.site.studio_solo_label', ['part' => $part['name']]) }}">{{ __('pages.site.studio_solo') }}</button>
                        <label class="s-ch-slider"><span>{{ __('pages.site.studio_level') }}</span><input type="range" min="0" max="1" step="0.05" value="1" data-level="{{ $part['key'] }}" aria-label="{{ __('pages.site.studio_level_label', ['part' => $part['name']]) }}"></label>
                        <label class="s-ch-slider"><span>{{ __('pages.site.studio_pan') }}</span><input type="range" min="-1" max="1" step="0.1" value="0" data-pan="{{ $part['key'] }}" aria-label="{{ __('pages.site.studio_pan_label', ['part' => $part['name']]) }}"></label>
                    </span>
                @endif
                @unless ($studio)
                    {{-- Your part: a small radio at the row's end (the one sung louder, "You"); the row itself plays its part alone. --}}
                    <button type="button" class="s-ch-mine" role="radio" aria-checked="{{ $you ? 'true' : 'false' }}" tabindex="{{ $you || (! $picked && $loop->first) ? '0' : '-1' }}" data-pick aria-label="{{ __('pages.landing.hero.mine_label', ['part' => $part['name']]) }}" data-label="{{ __('pages.landing.hero.mine_label', ['part' => $part['name']]) }}" data-label-you="{{ __('pages.landing.hero.your_part', ['part' => $part['name']]) }}"><span class="s-mine-off">{{ __('pages.landing.hero.mine_pick') }}</span><span class="s-mine-on">{{ __('pages.landing.hero.mine') }}</span></button>
                @else
                    <button type="button" class="s-ch-state" role="switch" aria-checked="{{ $isOn ? 'true' : 'false' }}" data-toggle aria-label="{{ __('pages.landing.hero.voice_switch', ['part' => $part['name']]) }}"><span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span></button>
                @endunless
            </div>
        @endforeach
    </div>
    @if ($song['words'] !== [])
        <div class="s-mix-words" aria-hidden="true"><p class="s-lyrics">@foreach ($song['words'] as $word)<span data-beat="{{ $word['beat'] }}">{{ $word['text'] }}</span>{{ $word['joins'] ? '' : ' ' }}@endforeach</p></div>
    @endif
    @isset($foot)
        <div class="s-mix-foot">{{ $foot }}</div>
    @endisset
    @if ($picked)
        <p class="sr-only">{{ __(($song['whole'] ?? false) ? 'pages.landing.hero.alt_whole' : 'pages.landing.hero.alt', ['song' => $song['title'], 'words' => \App\Support\Pages\Landing::sentence($song['words']), 'you' => $yourPart]) }}</p>
    @else
        <p class="sr-only">{{ __('pages.landing.hero.alt_open', ['song' => $song['title'], 'words' => \App\Support\Pages\Landing::sentence($song['words'])]) }}</p>
    @endif
</div>
