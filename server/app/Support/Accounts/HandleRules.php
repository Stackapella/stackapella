<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Models\HandleHold;
use App\Models\User;
use Normalizer;

/**
 * Is this handle allowed and free? (docs/trust-and-safety-plan.md, "Handles"): 3 to 20 lower-case
 * letters, digits and underscores, starting with a letter; never one of our own names or an
 * offensive word, also through look-alike letters; never taken or held after a deletion.
 */
final class HandleRules
{
    /** Look-alikes, mapped to the letter they pass for. */
    private const CONFUSABLES = ['0' => 'o', '1' => 'l', '!' => 'i', '|' => 'l', '3' => 'e', '4' => 'a', '@' => 'a', '5' => 's', '$' => 's', '7' => 't', '8' => 'b', '9' => 'g', '6' => 'g', '2' => 'z'];

    /** @var list<string>|null */
    private static ?array $offensive = null;

    /**
     * @return 'invalid'|'reserved'|'taken'|null null when it's free
     */
    public function problem(string $handle, ?User $for = null): ?string
    {
        if (preg_match('/^[a-z][a-z0-9_]{2,19}$/', $handle) !== 1) {
            return 'invalid';
        }

        // A "1" may stand for an l or an i, so a handle is read both ways.
        foreach (['l', 'i'] as $one) {
            $problem = $this->wordsProblem(self::skeleton($handle, $one), array_values(array_filter(array_map(static fn (string $part): string => self::skeleton($part, $one), explode('_', $handle)))));

            if ($problem !== null) {
                return $problem;
            }
        }

        $taken = User::query()->where('handle', $handle)->when($for !== null, fn ($query) => $query->whereKeyNot($for?->id))->exists()
            || HandleHold::query()->where('handle', $handle)->when($for !== null, fn ($query) => $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $for?->id)))->where(fn ($query) => $query->whereNull('held_until')->orWhere('held_until', '>', now()))->exists();

        return $taken ? 'taken' : null;
    }

    /**
     * @param  list<string>  $words  the handle's words, as a reader sees them ("help_desk" is "help" and "desk")
     * @return 'invalid'|'reserved'|null
     */
    private function wordsProblem(string $skeleton, array $words): ?string
    {
        foreach ((array) config('handles.protected') as $word) {
            if (str_contains($skeleton, self::skeleton((string) $word))) {
                return 'reserved';
            }
        }

        foreach ((array) config('handles.reserved') as $word) {
            $word = self::skeleton((string) $word);
            if ($skeleton === $word || in_array($word, $words, true)) {
                return 'reserved';
            }
        }

        // Long offensive words anywhere; short ones only as a word of their own, so "peacock" and
        // "grape" stay fine.
        foreach (self::offensive() as $word) {
            if ((strlen($word) >= 5 && str_contains($skeleton, $word)) || $skeleton === $word || in_array($word, $words, true)) {
                return 'invalid';
            }
        }

        return null;
    }

    /**
     * What a handle looks like to a reader: accents removed, look-alikes turned into the letters
     * they pass for, underscores dropped, and "rn" read as "m", "vv" as "w".
     */
    public static function skeleton(string $text, string $one = 'l'): string
    {
        $text = str_replace(['1', '!'], $one, strtolower($text));
        $plain = Normalizer::normalize($text, Normalizer::FORM_D);
        $text = (string) preg_replace('/\p{Mn}/u', '', is_string($plain) ? $plain : $text);
        $text = strtr($text, self::CONFUSABLES);
        $text = str_replace(['_', '-', '.', 'rn', 'vv'], ['', '', '', 'm', 'w'], $text);

        return (string) preg_replace('/[^a-z]/', '', $text);
    }

    /**
     * @return list<string>
     */
    private static function offensive(): array
    {
        if (self::$offensive === null) {
            $lines = file(resource_path('data/offensive-words.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$offensive = array_values(array_map(self::skeleton(...), array_filter($lines, static fn (string $line): bool => ! str_starts_with($line, '#'))));
        }

        return self::$offensive;
    }
}
