<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\HtmlString;

/**
 * The First steps to belting course page's logic (site/courses/belting, Hillel's design of 2026-11-18),
 * kept out of the view: a heading with its phrase set in the design's serif italics, and the headline stepped down like
 * a call at its comma ("Bright," on the high line, "not loud." on the low one). Both work on the words as the language
 * has them, so a translation that words a phrase differently just leaves the heading plain. And the drawings' scales: the
 * calling contour's (semitones from + 3 at the top to - 2, 34 units apart; a beat 90 units, at 80 a minute) and the
 * demo's spectrum (0 to 5,000 Hz across 600 units).
 */
final class BeltingPage
{
    /**
     * The heading's words, escaped, with each phrase (as escaped) wrapped in <em> where it first appears.
     *
     * @param  string|list<string>  $phrases
     */
    public static function em(string $text, string|array $phrases): HtmlString
    {
        $out = e($text);

        foreach ((array) $phrases as $phrase) {
            $find = e($phrase);
            $at = $find === '' ? false : strpos($out, $find);

            if ($at !== false) {
                $out = substr_replace($out, '<em>'.$find.'</em>', $at, strlen($find));
            }
        }

        return new HtmlString($out);
    }

    /**
     * The headline as its two steps, split after its first comma; one step when it has none.
     *
     * @return list<string>
     */
    public static function steps(string $title): array
    {
        $at = strpos($title, ',');
        $rest = $at === false ? '' : trim(substr($title, $at + 1));

        return $rest === '' ? [$title] : [substr($title, 0, (int) $at + 1), $rest];
    }

    /** The calling contour's line for a semitone, + 3 (the top) to - 2. */
    public static function semitoneY(int $semitone): int
    {
        return 34 + (3 - $semitone) * 34;
    }

    /** The calling contour's place for a beat, 90 units a beat from 66. */
    public static function beatX(float $beat): int
    {
        return (int) round(66 + $beat * 90);
    }

    /** The spectrum's place for a frequency, 0 to 5,000 Hz across 600 units. */
    public static function hzX(int $hz): int
    {
        return (int) round($hz * 0.12);
    }
}
