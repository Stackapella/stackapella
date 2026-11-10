<?php

declare(strict_types=1);

namespace App\Support\Pages;

use InvalidArgumentException;

/**
 * Whether a page's view exists yet: the backend's routes for a page another agent builds answer a
 * real 404 (and stay out of the sitemap) until its view lands. Asked of the view finder, so a view
 * added from elsewhere (a test's) counts too.
 */
final class Views
{
    public static function has(string $name): bool
    {
        try {
            app('view')->getFinder()->find($name);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
