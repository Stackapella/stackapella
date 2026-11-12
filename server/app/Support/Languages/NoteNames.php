<?php

declare(strict_types=1);

namespace App\Support\Languages;

use Illuminate\Support\Facades\App;

/**
 * A note's or a key's name as the page's language names notes: Spanish uses fixed Do (Do, Re, Mi, Fa, Sol, La, Si),
 * as the app does (frontend's Spanish walk, 2026-11-12: "C mayor" beside the app's "Do mayor"). English, and any
 * language that names notes by letter, keeps the name exactly as given.
 */
final class NoteNames
{
    /** @var array<string, array<string, string>> each letter's name, by language */
    private const NAMES = [
        'es' => ['C' => 'Do', 'D' => 'Re', 'E' => 'Mi', 'F' => 'Fa', 'G' => 'Sol', 'A' => 'La', 'B' => 'Si'],
    ];

    /** "E♭4" is "Mi♭4" in Spanish; "Eb" is "Mi♭" and "Fm" "Fa menor"; anything not a note's name is left as it is. */
    public static function name(string $note): string
    {
        $names = self::NAMES[App::getLocale()] ?? null;
        // A minor key as the song file writes it ("Fm"): "Fa menor", in the carols' own words for a minor key.
        if ($names !== null && preg_match('/^([A-G][b#♭♯]?)m$/u', $note, $minor) === 1) {
            return (string) __('site.carols.key_minor', ['note' => self::name($minor[1])]);
        }
        if ($names === null || preg_match('/^([A-G])([b#♭♯]?)(-?\d*)$/u', $note, $m) !== 1) {
            return $note;
        }

        return $names[$m[1]].strtr($m[2], ['b' => '♭', '#' => '♯']).$m[3];
    }
}
