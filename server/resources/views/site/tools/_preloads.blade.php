{{-- A tool's modules, fetched beside the stylesheet (docs/performance.md, 2026-11-03): page.js only runs once site.css has
     arrived, then imports the tool, which imports the rest a round trip a level; listed here they come at once. The list
     is the tool's whole static import graph (web/js/public/tools/preloads.json, via App\Support\Site\ToolPreloads), only
     our own same-origin /js/ modules: a module script loads in CORS mode without integrity, as a plain modulepreload does. --}}
@foreach ($preloads as $href)
    <link rel="modulepreload" href="{{ $href }}">
@endforeach
