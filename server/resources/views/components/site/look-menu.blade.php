{{-- Language and appearance, one pill in a header (Hillel, 2026-11-16: "combine the two buttons"): the site header's
     (x-site.header) and the home page's own top line (pages/landing) both use it. The pill shows a globe and the
     language's code, so someone who doesn't read English can still find their language; with only English open it
     shows the look's own icon. Its menu holds Language (each open language by its own name: LanguageMenu, its links
     naming their language, the choice kept in `sa_lang` by js/public/lang-menu.js), Appearance (Light, Dark or Auto,
     the same stored choice as before through theme-boot's choose(); Auto follows the device) and, on a phone, Game
     mode, the same switch as the header's (its pill is the header's from 900 px). A <details>, so the languages work
     with no script; js/public/look-menu.js adds the keys (arrows, Escape, focus kept inside while open), the current
     values in the pill's name and aria-expanded. The rules are css/input.css's "Language and appearance" block, which
     css/game-toggle.css copies for the home page. `appearance` false leaves light and dark out; `game` false leaves
     Game mode out. --}}
@props(['appearance' => true, 'game' => true, 'id' => 'look'])
@php($languages = \App\Support\Languages\LanguageMenu::for(request()))
@php($here = $languages === null ? null : collect($languages['choices'])->firstWhere('current', true))
<details {{ $attributes->class(['s-look', 's-look--lang' => $languages !== null]) }} data-look-menu>
    <summary class="s-look-btn" aria-label="{{ __('pages.nav.look') }}{{ $here !== null ? ': '.$here['name'] : '' }}" data-look-name="{{ __('pages.nav.look') }}">
        @if ($languages !== null)
            <x-site.icon name="globe" size="18" /><span class="s-look-code" aria-hidden="true">{{ strtoupper($languages['current']) }}</span>
        @else
            <span class="s-look-in-light"><x-site.icon name="sun" size="18" /></span><span class="s-look-in-dark"><x-site.icon name="moon" size="18" /></span>
        @endif
    </summary>
    <div class="s-look-panel" role="group" aria-label="{{ __('pages.nav.look') }}">
        @if ($languages !== null)
            <div class="s-look-group" data-lang-menu>
                <p class="s-look-h" id="{{ $id }}-lang">{{ __('pages.nav.look_language') }}</p>
                <ul class="s-look-langs" role="list" aria-labelledby="{{ $id }}-lang">
                    @foreach ($languages['choices'] as $choice)
                        <li><a href="{{ $choice['url'] }}" hreflang="{{ $choice['code'] }}" lang="{{ $choice['code'] }}" data-lang-choice="{{ $choice['code'] }}" @if ($choice['current']) aria-current="true" @endif><span>{{ $choice['name'] }}</span><x-site.icon name="check" size="18" /></a></li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($appearance)
            <div class="s-look-group">
                <p class="s-look-h" id="{{ $id }}-mode">{{ __('pages.nav.appearance') }}</p>
                <div class="s-look-seg" role="group" aria-labelledby="{{ $id }}-mode">
                    @foreach (['light' => 'light', 'dark' => 'dark', 'system' => 'auto'] as $mode => $word)
                        <button type="button" data-look-mode="{{ $mode }}" aria-pressed="false">{{ __('pages.nav.'.$word) }}</button>
                    @endforeach
                </div>
            </div>
        @endif
        @if ($game)
            <div class="s-look-group s-look-game"><x-site.game-toggle size="row" /></div>
        @endif
    </div>
</details>
<script type="module" src="/js/public/look-menu.js"></script>
