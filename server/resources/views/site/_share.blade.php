{{-- Share this page (docs/retention-plan.md, "Sharing": Hillel wants sharing everywhere): one secondary button beside the
     page's own primary, never a new band. js/public/share.js opens the device's share sheet, else copies the link; the
     link carries ?ref=share-<surface> so its arrivals count, and the share counts as sh:<surface>.<method>.
     Pass `surface` (a config counts.share_surfaces name), `url` (the page's canonical address), `title`, `name` when
     the share text should say less than the title (a collection's own name, not its search title), or a whole `text`,
     `lg` for a hero's large button, and `label` for words of its own ("Share with your choir"). --}}
<button type="button" @class(['btn-secondary', 'btn-lg' => $lg ?? false]) data-share data-surface="{{ $surface }}" data-url="{{ $url }}" data-title="{{ $title }}" data-text="{{ $text ?? __('site.share.text', ['title' => $name ?? $title]) }}" data-copied="{{ __('site.share.copied') }}"><x-site.icon name="link" size="18" /> <span data-share-label>{{ $label ?? __('site.share.button') }}</span></button>
@once
    <script type="module" src="/js/public/share.js"></script>
@endonce
