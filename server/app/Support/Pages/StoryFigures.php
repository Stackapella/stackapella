<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * A song story's pictures on its page (Hillel, 2026-11-12: "add images and links to the songs pages to make them more
 * immersive"): the story places each with `<!--figure:n-->` on its own line in a section or a timeline row, and gives
 * story['images'] keyed by n (src, srcset, width, height, alt, caption, date, library, callNumber, credit, licence,
 * licenceName, licenceUrl, sourceUrl) and story['hero'], the one drawn by the story's heading. A placeholder without its
 * picture draws nothing (app:stories:check fails a story that names a missing one). Until a story has pictures, this
 * changes nothing.
 */
final class StoryFigures
{
    /**
     * A section's or row's html with each placeholder as its figure (site._story-figure).
     *
     * @param  array<string, mixed>  $story
     */
    public static function fill(string $html, array $story): string
    {
        if (! str_contains($html, '<!--figure:')) {
            return $html;
        }

        return (string) preg_replace_callback('/<!--figure:([0-9]+)-->/', static function (array $m) use ($story): string {
            $image = self::image($story, $m[1]);

            return $image === null ? '' : view('site._story-figure', ['image' => $image, 'lazy' => true])->render();
        }, $html);
    }

    /**
     * A picture's date, library and call number, and credit, as one line.
     *
     * @param  array<string, mixed>  $image
     */
    public static function credit(array $image): string
    {
        $library = implode(', ', array_filter([$image['library'] ?? null, $image['callNumber'] ?? null], static fn (mixed $v): bool => is_string($v) && $v !== ''));

        return implode(' · ', array_filter([$image['date'] ?? null, $library, $image['credit'] ?? null], static fn (mixed $v): bool => is_string($v) && $v !== ''));
    }

    /**
     * The story's lead picture, or null.
     *
     * @param  array<string, mixed>  $story
     * @return array<string, mixed>|null
     */
    public static function hero(array $story): ?array
    {
        $key = $story['hero'] ?? null;

        return is_string($key) || is_int($key) ? self::image($story, (string) $key) : null;
    }

    /**
     * One picture by its number, when the story gives it whole.
     *
     * @param  array<string, mixed>  $story
     * @return array<string, mixed>|null
     */
    private static function image(array $story, string $key): ?array
    {
        $image = ((array) ($story['images'] ?? []))[$key] ?? null;

        return is_array($image) && is_string($image['src'] ?? null) && (int) ($image['width'] ?? 0) > 0 && (int) ($image['height'] ?? 0) > 0 ? $image : null;
    }
}
