{{-- The guides of a step on an audience page (site/for-path): a list, each its title and one line, to its own page.
     `more` adds All the guides. --}}
<div class="s-headrow">
    <h3 class="s-kick">{{ __('pages.guides.eyebrow') }}</h3>
    @if ($more)
        <a href="/guides" class="s-arrow">{{ __('pages.guides.all') }} <x-site.icon name="arrow" size="16" /></a>
    @endif
</div>
<ul class="s-aud-guides">
    @foreach ($guides as $guide)
        <li><a href="{{ $guide['url'] }}"><b>{{ $guide['name'] }}</b><span>{{ $guide['summary'] }}</span><i aria-hidden="true"><x-site.icon name="arrow" size="16" /></i></a></li>
    @endforeach
</ul>
