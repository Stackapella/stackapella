<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * /next-final's data: the candidate for the final next landing page, built from /next-claude with what was worth
 * taking from the other three (Hillel, 2026-11-15: "lets say we go with Claude design, please have a UI/UX expert
 * agent review all 4 designs and create and deploy a fifth potential final design"). The stage is /next-claude's own
 * (NextClaude), its ways into the app marked as this page's.
 */
final class NextFinal
{
    /** The page's path, in English; /es/next-final is its Spanish. */
    public const PATH = '/next-final';

    /** What its links into the app and the site carry, so what it sends can be told from the landing's. */
    public const REF = 'next-final';

    /** @return array<string, mixed>|null */
    public static function stage(): ?array
    {
        return NextClaude::stage(self::REF);
    }

    /** A singer's initials for their bubble in the choir card ("Priya Shah" is PS): each name's first letter, two at most. */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }
}
