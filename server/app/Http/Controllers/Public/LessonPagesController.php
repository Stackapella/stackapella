<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\Lessons;
use App\Support\Pages\PageGone;
use App\Support\Site\SitePages;
use App\Support\Site\StructuredData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * A course's public lesson page (docs/seo-lesson-pages-plan.md): /courses/<course>/<page>, the lesson's (or unit's)
 * teaching as text, the steps you sing, its "Did you know?", the course's safety line, Sing this lesson into the app,
 * the pages before and after it, About Stackapella and the free tools. A lesson's or unit's id 301s to its page, so a
 * retitled lesson never breaks a link. No session; cached like the other public pages.
 */
final class LessonPagesController extends Controller
{
    public function show(Request $request, string $course, string $page): Response|RedirectResponse
    {
        $found = Lessons::page($course, $page);
        if ($found === null) {
            $slug = Lessons::slugFor($course, $page);

            return $slug === null ? PageGone::response(isset(Lessons::courses()[$course]) ? Lessons::courses()[$course]['up'] : '/courses') : redirect(Landing::withQuery($request, '/courses/'.$course.'/'.$slug), 301);
        }

        $url = Hosts::publicUrl().$found['url'];
        $description = $found['description'] ?? (string) __('site.lessons.description', ['name' => $found['name'], 'course' => $found['courseName']]);
        $meta = ['title' => SitePages::title((string) $found['title']), 'description' => $description, 'url' => $url];
        $courseUrl = Hosts::publicUrl().$found['up'];

        return response()->view('site.lesson', [
            'title' => $meta['title'],
            'meta' => $meta,
            'ld' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'LearningResource',
                    'name' => $found['name'],
                    'description' => $description,
                    'url' => $url,
                    'inLanguage' => 'en',
                    'learningResourceType' => 'lesson',
                    'isAccessibleForFree' => true,
                    'educationalLevel' => 'beginner',
                    ...($found['minutes'] ? ['timeRequired' => 'PT'.$found['minutes'].'M'] : []),
                    'isPartOf' => ['@type' => 'Course', 'name' => $found['courseName'], 'url' => $courseUrl, 'provider' => ['@type' => 'Organization', 'name' => (string) __('meta.brand'), 'sameAs' => Hosts::publicUrl().'/']],
                ],
                StructuredData::breadcrumbs([[(string) __('pages.site.courses'), Hosts::publicUrl().'/courses'], [(string) $found['courseName'], $courseUrl], [(string) $found['name'], $url]]),
            ],
            'area' => 'courses',
            'page' => $found,
            // The real voices its lessons play, where a beta singer's recording is published.
            'clips' => Lessons::clips(array_values(array_map(static fn (array $lesson): string => (string) $lesson['id'], (array) $found['lessons']))),
            // "Try one round" (docs/lesson-page-try-it-plan.md): dark until the lesson_try switch is on.
            'tryOn' => app(Flags::class)->on('lesson_try'),
            'tryWords' => (array) Lang::get('site.lessons.tryWords'),
            'unitPages' => array_values(array_filter(Lessons::pages($course), static fn (array $p): bool => $p['unitNumber'] === $found['unitNumber'])),
            'directory' => SitePages::directory(null, []),
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            // About Stackapella's way in: this lesson itself.
            'try' => (string) $found['start'],
        ], 200, SongPagesController::headers());
    }
}
