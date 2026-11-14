<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * The lesson pages Lessons::pages built this request, by language and course: scoped (AppServiceProvider), so they're
 * never kept past it. A static kept them across a long-running process's requests (a parallel test worker, a queue
 * worker), so a course that opened still had the empty pages it had while closed.
 */
final class LessonPagesBuilt
{
    /** @var array<string, list<array<string, mixed>>> */
    public array $pages = [];
}
