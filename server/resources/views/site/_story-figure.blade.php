{{-- A story's picture (App\Support\Pages\StoryFigures): served from our own host, sized, lazy below the fold, with its
     caption, date, library and call number, credit, licence and the original's scan, all shown. --}}
<figure class="s-fig">
    <img src="{{ $image['src'] }}" @if (is_string($image['srcset'] ?? null)) srcset="{{ $image['srcset'] }}" sizes="(min-width: 1024px) 680px, 100vw" @endif width="{{ (int) $image['width'] }}" height="{{ (int) $image['height'] }}" alt="{{ $image['alt'] ?? '' }}" @if ($lazy) loading="lazy" @endif decoding="async">
    <figcaption>
        @if (is_string($image['caption'] ?? null) && $image['caption'] !== '')
            <span class="s-fig-cap">{!! $image['caption'] !!}</span>
        @endif
        <span class="s-fig-meta">
            {{ \App\Support\Pages\StoryFigures::credit($image) }}
            @if (is_string($image['licenceName'] ?? null) && $image['licenceName'] !== '')
                · @if (is_string($image['licenceUrl'] ?? null))<a href="{{ $image['licenceUrl'] }}" class="s-lk" rel="license">{{ $image['licenceName'] }}</a>@else{{ $image['licenceName'] }}@endif
            @endif
            @if (is_string($image['sourceUrl'] ?? null))
                · <a href="{{ $image['sourceUrl'] }}" class="s-lk">{{ __('pages.song.story.original') }}</a>
            @endif
        </span>
    </figcaption>
</figure>
