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
        // The course lessons: the courses lead's Spanish for every course (2026-11-11).
        'es' => ['courses.lesson'],
        'de' => [],
    ],

];
