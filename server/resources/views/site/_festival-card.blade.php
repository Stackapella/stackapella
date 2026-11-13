{{-- A festival's card (App\Support\Pages\Festivals::card; Hillel's Jewish Songs redesign, 2026-11-12): the festival large,
     beside it how soon (or until when it's on) with its first day, then its songs (at most three) to learn in time, each
     to its page, and the way to its collection. The Jewish songs page's countdown and /songs's "Coming up" both. Its
     data- attributes let js/public/coming-up.js say it again on the visitor's own day. `id` names it; `hidden` hides it
     until the script picks it. --}}
<aside @class(['s-jcount', $class ?? '', 's-jcount-lamp' => $card['key'] === 'hanukkah' && ($lamp ?? true)]) aria-labelledby="{{ $id }}-k" data-festival data-key="{{ $card['key'] }}" data-band="{{ $card['band'] }}" data-from="{{ $card['from'] }}" data-until="{{ $card['until'] }}" data-ahead="{{ $card['ahead'] }}" @if ($card['evening']) data-evening @endif data-t-kick="{{ $card['kick'] }}" data-t-kick-on="{{ $card['kickOn'] }}" data-t-one="{{ $card['one'] }}" data-t-other="{{ $card['other'] }}" data-t-tonight="{{ $card['tonight'] }}" data-t-today="{{ $card['today'] }}" @if ($hidden ?? false) hidden @endif>
    {{-- Hanukkah's card carries the lamp, small in its top-right corner (docs/design-rules.md, "A motif draws its whole object"),
         except where the page already draws it (/songs's Jewish panel: `lamp` false), so it's never drawn twice. --}}
    @if ($card['key'] === 'hanukkah' && ($lamp ?? true))
        {{ \App\Support\Pages\Hanukkiah::svg('s-hk s-hk-card') }}
    @endif
    <p id="{{ $id }}-k" class="s-jcount-k" data-kick>{{ $card['on'] ? $card['kickOn'] : $card['kick'] }}</p>
    <div class="s-jcount-top">
        <p class="s-jcount-n s-xserif">{{ $card['name'] }}</p>
        <div class="s-jcount-when">
            <p class="s-jcount-d" data-when @if ($card['on']) hidden @endif>{{ $card['when'] }}</p>
            <p class="s-jcount-date" data-start @if ($card['on']) hidden @endif>{{ $card['start'] }}</p>
            <p class="s-jcount-date" data-until @unless ($card['on']) hidden @endunless>{{ $card['untilLine'] }}</p>
        </div>
    </div>
    @if ($card['learn'] !== null)
        <p class="s-jcount-learn">{{ $card['learn'] }}</p>
        <ul class="s-jcount-songs">
            @foreach ($card['songs'] as $song)
                <li><a href="{{ $song['url'] }}"><b translate="no">{{ $song['title'] }}</b>@if (($song['level'] ?? null) !== null)<span>{{ $song['level'] }}</span>@endif<x-site.icon name="arrow" size="16" /></a></li>
            @endforeach
            {{-- Our guide to the festival's songs for a choir, when there is one (the Hanukkah guide). --}}
            @php($guide = 'site.collections.jewish-songs.count_guide.'.$card['key'])
            @if (trans()->has($guide))
                <li><a href="/guides/{{ $card['key'] }}-songs-for-choir"><b>{{ __($guide) }}</b><span>{{ __('site.collections.jewish-songs.count_guide_tag') }}</span><x-site.icon name="arrow" size="16" /></a></li>
            @endif
        </ul>
    @endif
    @if ($card['link'] !== null)
        <a href="{{ $card['link']['url'] }}" class="s-jcount-all">{{ $card['link']['label'] }}&#8288;<x-site.icon name="arrow" size="16" /></a>
    @endif
</aside>
