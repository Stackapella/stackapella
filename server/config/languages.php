<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The site in other languages (docs/languages-plan.md, App\Support\Languages)
|--------------------------------------------------------------------------
|
| The public pages each language serves under /<lang>/, by route name: only pages whose own words are
| in that language (a page whose words are still English stays out, so search never finds an English
| page at a Spanish address). The language's switch (lang_es, lang_de) opens them; until then they
| answer only locally and in tests, to check a draft. Each listed page gets hreflang alternates, its
| links to other listed pages stay in the language, and the sitemap lists it.
|
*/

return [

    'site' => [
        // The courses lead's Spanish, area by area as the coordinator ordered (2026-11-11): the courses and lessons,
        // the songs and their part pages, the tools and warm-ups, the guides (each once its Spanish file is written:
        // resources/guides/es/<slug>.md), the reference pages, help, and the site's own pages, the home page (`home`: the
        // landing, at /es). Not yet: legal (English, by Hillel's decision), press and what's new.
        'es' => [
            'home',
            'courses.index', 'courses.page', 'courses.free', 'courses.lesson',
            'songs.index', 'songs.carols', 'songs.collection', 'songs.page', 'songs.part',
            // tools.embed before tools.page, as the English routes go: /tools/embed isn't a tool.
            'tools.index', 'tools.embed', 'tools.page', 'warmups.index', 'warmups.page',
            'guides.index', 'guides.page',
            'intervals.index', 'intervals.page', 'chords.index', 'chords.page', 'voice-types.index', 'voice-types.page',
            'help.index', 'help.page',
            'pricing.page', 'features.index', 'features.page', 'compare.index', 'compare.page', 'for.page',
            'integrations.index', 'integrations.page', 'about.page', 'how.page', 'open-source.page', 'selfhost.page',
        ],
        'de' => [],
    ],

];
