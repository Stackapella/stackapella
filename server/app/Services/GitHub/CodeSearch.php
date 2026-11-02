<?php

declare(strict_types=1);

namespace App\Services\GitHub;

use Illuminate\Support\Facades\Http;

/**
 * GitHub's code search, for code watch (docs/code-watch-plan.md): which public repositories hold
 * an exact string.
 */
final class CodeSearch
{
    public function configured(): bool
    {
        return (string) config('codewatch.github_token') !== '';
    }

    /**
     * @return list<array{repo: string, url: string}> one row per repository
     */
    public function repositories(string $exact): array
    {
        $items = Http::withToken((string) config('codewatch.github_token'))->accept('application/vnd.github+json')->timeout(30)
            ->get('https://api.github.com/search/code', ['q' => '"'.str_replace('"', '', $exact).'"', 'per_page' => 100])
            ->throw()->json('items') ?? [];
        $repos = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $name = is_array($item) ? ($item['repository']['full_name'] ?? null) : null;

            if (is_string($name) && $name !== '') {
                $repos[$name] ??= ['repo' => $name, 'url' => (string) ($item['repository']['html_url'] ?? 'https://github.com/'.$name)];
            }
        }

        return array_values($repos);
    }
}
