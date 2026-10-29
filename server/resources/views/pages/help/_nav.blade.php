{{-- The guide's contents: each group and its pages, the one showing marked. --}}
<nav class="s-guide-nav" aria-label="{{ __('pages.help.contents') }}">
    @foreach ($groups as $group)
        <p class="s-guide-group">{{ $group['title'] }}</p>
        <ul>
            @foreach ($group['pages'] as $page)
                <li><a href="/help/{{ $page['slug'] }}" @if (($current ?? null) === $page['slug']) aria-current="page" @endif>{{ $page['title'] }}</a></li>
            @endforeach
        </ul>
    @endforeach
</nav>
