{{-- A real screen of the app (the guide's screenshots, web/icons/help: `PORT=8410 web/tool/flows.sh
     help_shots` makes them), in the visitor's theme: the light one, or the dark one in a dark theme
     (theme-boot sets it before the page paints, so the right one shows first). webp with a png
     fallback, its own size, loaded when it nears the screen. A phone's tall screen is framed and
     shows its top. Nothing at all while a shot is missing. --}}
@props(['id', 'alt'])
@php($shot = \App\Support\Site\HelpGuide::shot($id))
@if ($shot !== null)
    <figure @class(['s-appshot', 's-appshot-tall' => $shot['light']['height'] > $shot['light']['width']])>
        @foreach ($shot as $mode => $file)
            <picture class="s-shot-{{ $mode }}">
                <source srcset="{{ $file['webp'] }}" type="image/webp">
                <img src="{{ $file['png'] }}" alt="{{ $alt }}" width="{{ $file['width'] }}" height="{{ $file['height'] }}" loading="lazy" decoding="async">
            </picture>
        @endforeach
    </figure>
@endif
