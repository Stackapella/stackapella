{{-- The guide's search: the index (/help/search-index.json) is fetched the first time it's used
     (js/public/help-search.js), then matched as you type. `$id` keeps two on one page apart. --}}
<div @class(['s-hsearch', 's-hsearch-hero' => $hero ?? false]) data-help-search="/help/search-index.json" data-none="{{ __('pages.help.search_none') }}" data-loading="{{ __('pages.help.search_loading') }}" data-one="{{ __('pages.help.search_count.0') }}" data-many="{{ __('pages.help.search_count.1') }}">
    <label for="{{ $id }}" class="sr-only">{{ __('pages.help.search') }}</label>
    <x-site.icon name="search" size="18" />
    <input id="{{ $id }}" class="input" type="search" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="{{ $id }}-results" autocomplete="off" spellcheck="false" placeholder="{{ ($hero ?? false) ? __('pages.help.search_placeholder') : __('pages.help.search_short') }}">
    <div id="{{ $id }}-results" role="listbox" aria-label="{{ __('pages.help.search') }}" class="s-hsearch-results" hidden></div>
    <p class="sr-only" aria-live="polite" data-status></p>
</div>
