<?php

declare(strict_types=1);

namespace App\Support\Mail;

use Illuminate\Support\HtmlString;

/**
 * An email's line with its *bold* phrases bold (resources/views/emails/_words.blade.php): every part
 * escaped, and no stray spaces, so a phrase runs straight into the punctuation after it. Only our
 * own words mark bold: a value put into them (a name, an address, a support reply) goes through
 * keep() first, so a "*" in it is printed as it is.
 */
final class Bold
{
    /** Stands for a "*" that is text, not a marker (a private-use character). */
    private const KEPT = "\u{E000}";

    /**
     * @param  string  $colour  the text colour of the email's skin (EmailSkin)
     */
    public static function line(string $line, string $colour = '#211a15'): HtmlString
    {
        $parts = explode('*', $line);
        $html = '';

        foreach ($parts as $i => $part) {
            $part = e(self::restore($part));
            $html .= $i % 2 === 1 ? '<strong class="sa-text" style="font-weight:700;color:'.e($colour).';">'.$part.'</strong>' : $part;
        }

        return new HtmlString($html);
    }

    /**
     * The line for the plain-text email: the markers dropped, a kept "*" printed.
     */
    public static function plain(string $line): string
    {
        return self::restore(str_replace('*', '', $line));
    }

    /**
     * Someone's text, to put into our words: its "*"s are text, never bold.
     */
    public static function keep(string $text): string
    {
        return str_replace('*', self::KEPT, $text);
    }

    /**
     * Our words with values in them, each value kept as it is.
     *
     * @param  array<string, string>  $values
     */
    public static function words(string $key, array $values = []): string
    {
        return (string) __($key, array_map(self::keep(...), $values));
    }

    private static function restore(string $text): string
    {
        return str_replace(self::KEPT, '*', $text);
    }
}
