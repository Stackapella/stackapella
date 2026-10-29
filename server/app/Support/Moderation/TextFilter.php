<?php

declare(strict_types=1);

namespace App\Support\Moderation;

use App\Support\Accounts\HandleRules;

/**
 * The rules for what people type (docs/trust-and-safety-plan.md, "Rules for anything people
 * type"), after Event Schedule's SpamWatch. No links at launch: a title with one is refused. Text
 * that matches the word lists, a phone number or an email, or a long run of one character, is
 * held for Hillel's look instead of refused, since most matches are innocent.
 */
final class TextFilter
{
    /** @var array<string, list<string>> */
    private static array $lists = [];

    /** The endings a link is most likely to use; "Mary.Jane" or "alto.Love" are names, not links. */
    private const TLDS = 'com|net|org|io|co|me|ly|app|dev|xyz|info|biz|ru|uk|de|fr|tv|gg|link|site|online|shop|store|club|top|live|fm|to|us|ca|au|in|nl|es|it|eu|pro|page|cc|ws|su|cn|tk|ml|ga|cf|gq|bit|ai|so|sh|is|vip|win|bet|cash|money|click|lol|fun|icu|cyou|rest|buzz|today|pw';

    public static function hasLink(string $text): bool
    {
        $text = mb_strtolower($text);

        return preg_match('~(?:https?:|ftp:|www\.)~u', $text) === 1
            // A domain: a word, a dot and a known ending, then the end, a space, a slash or punctuation.
            || preg_match('~[\p{L}\p{N}-]{2,}\.(?:'.self::TLDS.')(?=$|[\s/?#:,;!)])~u', $text) === 1;
    }

    public static function holds(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $lower = mb_strtolower($text);
        $squashed = (string) preg_replace('/\s+/u', ' ', $lower);
        $words = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $lower) ?: []));
        $skeletons = array_map(static fn (string $word): string => HandleRules::skeleton($word), $words);

        foreach (self::load('offensive-words') as $word) {
            if (in_array(HandleRules::skeleton($word), $skeletons, true)) {
                return true;
            }
        }

        foreach (self::load('held-phrases') as $phrase) {
            if (str_contains($squashed, $phrase)) {
                return true;
            }
        }

        return preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $text) === 1
            // A phone number: an international one, or 10 or more digits with spaces, dots or dashes
            // between (so a year span like 1818-2018 isn't one).
            || preg_match('/\+\d[\d\s.\-()]{7,}\d/', $text) === 1
            || preg_match('/(?:\d[\s.\-()]*){10,}/', $text) === 1
            || preg_match('/(.)\1{5,}/u', $text) === 1;
    }

    /**
     * @return list<string>
     */
    private static function load(string $name): array
    {
        if (! isset(self::$lists[$name])) {
            $lines = file(resource_path("data/{$name}.txt"), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$lists[$name] = array_values(array_map(static fn (string $line): string => mb_strtolower(trim($line)), array_filter($lines, static fn (string $line): bool => ! str_starts_with($line, '#'))));
        }

        return self::$lists[$name];
    }
}
