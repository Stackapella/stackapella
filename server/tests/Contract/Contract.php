<?php

declare(strict_types=1);

namespace Tests\Contract;

use RuntimeException;

/**
 * contract/api.md, read: the operations table, and each operation's response cases. The app's
 * api.js reads the same tables, so the two sides can't disagree about what exists.
 */
final class Contract
{
    /** What every beta.* operation may also answer (contract/api.md, "Templates and the beta"). */
    public const BETA = [
        403 => ['not_beta_singer', 'singer_revoked'],
    ];

    /** Every admin.* operation may also answer these, as admin.me does (contract/api.md, "Operations"). */
    public const ADMIN = [
        401 => ['unauthenticated'],
        403 => ['not_admin', 'second_factor_required', 'sudo_required'],
    ];

    /** The general rules' codes: any operation may answer with these (contract/api.md, "Errors"). */
    public const GENERAL = [
        400 => ['bad_request'],
        401 => ['unauthenticated'],
        403 => ['forbidden'],
        404 => ['not_found'],
        419 => ['csrf_mismatch'],
        422 => ['validation_failed'],
        426 => ['app_outdated'],
        429 => ['rate_limited'],
        500 => ['server_error'],
        423 => ['paused', 'read_only'],
        503 => ['unavailable'],
    ];

    /** @var array<string, array{method: string, path: string, who: string, since: string, cases: list<array{status: int, code: string|null, schema: string|null, example: string|null}>}>|null */
    private static ?array $operations = null;

    public static function directory(): string
    {
        return dirname(base_path()).'/contract';
    }

    /**
     * @return array<string, array{method: string, path: string, who: string, since: string, cases: list<array{status: int, code: string|null, schema: string|null, example: string|null}>}>
     */
    public static function operations(): array
    {
        if (self::$operations !== null) {
            return self::$operations;
        }

        $markdown = (string) file_get_contents(self::directory().'/api.md');
        $section = self::section($markdown, '## Operations');
        $operations = [];

        foreach (self::tableRows($section) as $cells) {
            if (count($cells) < 4 || preg_match('/^`([a-z][\w.]*)`$/i', $cells[0], $name) !== 1 || preg_match('/^`(GET|POST|PUT|PATCH|DELETE) (\/\S+)`$/', $cells[1], $route) !== 1) {
                continue;
            }

            $operations[$name[1]] = ['method' => $route[1], 'path' => $route[2], 'who' => $cells[2], 'since' => $cells[3], 'cases' => self::cases($markdown, $name[1])];
        }

        if ($operations === []) {
            throw new RuntimeException('contract/api.md lists no operations under "## Operations".');
        }

        return self::$operations = $operations;
    }

    /**
     * @return list<array{status: int, code: string|null, schema: string|null, example: string|null}>
     */
    private static function cases(string $markdown, string $operation): array
    {
        $section = preg_match('/^(#{3,4}) `'.preg_quote($operation, '/').'`$/m', $markdown, $heading) === 1
            ? self::section($markdown, $heading[0])
            : '';
        $cases = [];

        foreach (self::tableRows($section) as $cells) {
            if (count($cells) < 4 || preg_match('/^\d{3}$/', $cells[0]) !== 1) {
                continue;
            }

            $cases[] = [
                'status' => (int) $cells[0],
                'code' => self::unquote($cells[1]),
                'schema' => self::unquote($cells[2]),
                'example' => self::unquote($cells[3]),
            ];
        }

        return $cases;
    }

    private static function section(string $markdown, string $heading): string
    {
        $start = strpos($markdown, "\n".$heading."\n");

        if ($start === false) {
            return '';
        }

        $level = strspn($heading, '#');
        $rest = substr($markdown, $start + strlen($heading) + 2);

        // Up to the next heading of the same or a higher level.
        if (preg_match('/\n#{1,'.$level.'} /', $rest, $match, PREG_OFFSET_CAPTURE) === 1) {
            $rest = substr($rest, 0, $match[0][1]);
        }

        return $rest;
    }

    /**
     * @return list<list<string>>
     */
    private static function tableRows(string $text): array
    {
        $rows = [];

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, '|') || preg_match('/^\|[\s:|-]+\|$/', $line) === 1) {
                continue;
            }

            $rows[] = array_map('trim', explode('|', trim($line, '|')));
        }

        return $rows;
    }

    private static function unquote(string $cell): ?string
    {
        $cell = trim($cell, ' `');

        return $cell === '' ? null : $cell;
    }
}
