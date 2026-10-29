{{-- A harmony in the gallery: its share card, title, who sang it and its likes, linking to its
     page. --}}
<a href="{{ $harmony['pageUrl'] }}" class="s-card s-harmony-card">
    @if ($harmony['cardUrl'])
        <img src="{{ $harmony['cardUrl'] }}" alt="" width="1200" height="630" loading="lazy" class="s-harmony-image">
    @endif
    <span class="s-row">
        <b translate="no">{{ $harmony['title'] }}</b>
        @if ($harmony['featured'])
            <span class="s-badge s-badge-free">{{ __('pages.gallery_page.featured') }}</span>
        @endif
    </span>
    <span translate="no">{{ __('pages.gallery_page.sung_by', ['names' => collect($harmony['singers'])->pluck('name')->join(', ')]) }}</span>
    <span>{{ trans_choice('pages.gallery_page.likes', (int) $harmony['likes'], ['count' => (int) $harmony['likes']]) }}@if ($harmony['openParts'] !== []) · {{ __('pages.gallery_page.open_part') }}@endif</span>
</a>
