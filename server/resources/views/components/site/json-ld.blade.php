{{-- Structured data for search (App\Support\Site\StructuredData): data, never run as a script. Tags,
     ampersands and quotes are escaped, so nothing in the data can close the script tag. --}}
@props(['data'])
@foreach ($data as $item)
    <script type="application/ld+json">@json($item, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@endforeach
