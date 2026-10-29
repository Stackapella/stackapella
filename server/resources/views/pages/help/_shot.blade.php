{{-- A screenshot of the app, in the reader's theme (the light one, or the dark one in a dark theme),
     linking to the full-size picture. A phone's tall picture is drawn narrower. --}}
@php($tall = $shot['light']['height'] > $shot['light']['width'])
<figure @class(['s-shot', 's-shot-tall' => $tall])>
    @foreach ($shot as $mode => $file)
        <a href="{{ $file['png'] }}" class="s-shot-frame s-shot-{{ $mode }}" target="_blank" rel="noopener" aria-label="{{ __('pages.help.shot_open', ['alt' => $alt]) }}">
            <picture>
                <source srcset="{{ $file['webp'] }}" type="image/webp">
                <img src="{{ $file['png'] }}" alt="{{ $alt }}" width="{{ $file['width'] }}" height="{{ $file['height'] }}" loading="lazy" decoding="async">
            </picture>
        </a>
    @endforeach
</figure>
