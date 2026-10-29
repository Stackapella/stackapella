{{-- One of the site's icons (App\Support\Site\Icons), in the text's colour. --}}
@props(['name', 'size' => 20])
{{ \App\Support\Site\Icons::svg($name, (int) $size) }}
