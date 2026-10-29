<?php

declare(strict_types=1);

namespace App\Support\Legal;

use RuntimeException;

/**
 * A legal document (resources/legal/<lang>/<name>.md) or a guide (resources/guides/<lang>/...) as
 * the app shows it: its version, whether the lawyer has reviewed it, and the text as headings,
 * paragraphs and lists. Never HTML: the app shows every block as text, with **bold** marked.
 *
 * On a self-hosted install (config/selfhost.php) the legal texts are the operator's own, from
 * selfhost.legal_path; one not written yet says so, and Stackapella's are never shown. The guides
 * are read from content.guides, so an install without them simply has none.
 */
final class LegalText
{
    /**
     * @return array{version: int, reviewed: bool, language: string, title: string, description: string|null, song: string|null, blocks: list<array{type: string, level: int|null, text: string|null, items: list<string>|null}>}
     */
    public function read(string $name, string $language = 'en', string $folder = 'legal'): array
    {
        if ($folder === 'legal' && config('selfhost.enabled') === true) {
            return $this->operators($name, $language);
        }

        return $this->parse($this->root($folder), $name, $language);
    }

    /**
     * Whether the text is there (a guide an install has kept, a legal page that's written).
     */
    public function exists(string $name, string $folder = 'legal'): bool
    {
        return is_file($this->root($folder)."/en/{$name}.md");
    }

    private function root(string $folder): string
    {
        return $folder === 'guides' ? (string) config('content.guides') : resource_path($folder);
    }

    /**
     * A self-hosted install's own legal text, or, until its operator writes it, a page saying so
     * (titled as Stackapella's would be, so the links still read right).
     *
     * @return array{version: int, reviewed: bool, language: string, title: string, description: string|null, song: string|null, blocks: list<array{type: string, level: int|null, text: string|null, items: list<string>|null}>}
     */
    private function operators(string $name, string $language): array
    {
        $local = (string) config('selfhost.legal_path');

        if (is_file("{$local}/{$language}/{$name}.md") || is_file("{$local}/en/{$name}.md")) {
            return $this->parse($local, $name, $language);
        }

        $title = is_file(resource_path("legal/en/{$name}.md")) ? $this->parse(resource_path('legal'), $name, 'en')['title'] : ucfirst(str_replace('-', ' ', $name));
        $page = $this->parse(resource_path('legal-self-hosted'), 'not-written', $language);
        $fill = static fn (?string $text): ?string => $text === null ? null : str_replace(':title', $title, $text);

        return [
            ...$page,
            'title' => $title,
            'blocks' => array_map(static fn (array $block): array => [...$block, 'text' => $fill($block['text']), 'items' => $block['items'] === null ? null : array_map(static fn (string $item): string => (string) $fill($item), $block['items'])], $page['blocks']),
        ];
    }

    /**
     * @return array{version: int, reviewed: bool, language: string, title: string, description: string|null, song: string|null, blocks: list<array{type: string, level: int|null, text: string|null, items: list<string>|null}>}
     */
    private function parse(string $root, string $name, string $language): array
    {
        $path = "{$root}/{$language}/{$name}.md";

        if (! is_file($path)) {
            $language = 'en';
            $path = "{$root}/en/{$name}.md";
        }

        // The legal address is a setting (SITE_LEGAL_EMAIL), so the texts name it as it's set.
        $text = is_file($path) ? str_replace('legal@stackapella.com', (string) config('site.legal_email'), (string) file_get_contents($path)) : '';

        if (! is_file($path) || preg_match('/^---\n(.*?)\n---\n(.*)$/s', $text, $parts) !== 1) {
            throw new RuntimeException("No legal text [{$name}], or it has no front matter.");
        }

        preg_match('/^version:\s*(\d+)/m', $parts[1], $version);
        preg_match('/^reviewed:\s*(true|false)/m', $parts[1], $reviewed);
        preg_match('/^title:\s*(.+)$/m', $parts[1], $title);
        preg_match('/^description:\s*(.+)$/m', $parts[1], $description);
        preg_match('/^song:\s*([a-z0-9-]+)\s*$/m', $parts[1], $song);

        $documentTitle = trim($title[1] ?? $name);
        $blocks = [];
        $paragraph = [];
        $list = null;

        foreach ([...explode("\n", $parts[2]), ''] as $line) {
            $line = rtrim($line);
            $isItem = preg_match('/^- (.+)$/', $line, $item) === 1;
            $continues = $list !== null && str_starts_with($line, '  ') && trim($line) !== '';

            if ($paragraph !== [] && ($line === '' || $isItem || str_starts_with($line, '#'))) {
                $blocks[] = ['type' => 'paragraph', 'level' => null, 'text' => implode(' ', $paragraph), 'items' => null];
                $paragraph = [];
            }

            if ($list !== null && ! $isItem && ! $continues) {
                $blocks[] = ['type' => 'list', 'level' => null, 'text' => null, 'items' => $list];
                $list = null;
            }

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.+)$/', $line, $heading) === 1) {
                if (strlen($heading[1]) === 1) {
                    $documentTitle = $heading[2];
                } else {
                    $blocks[] = ['type' => 'heading', 'level' => strlen($heading[1]), 'text' => $heading[2], 'items' => null];
                }
            } elseif ($isItem) {
                $list ??= [];
                $list[] = $item[1];
            } elseif ($continues) {
                $list[count($list) - 1] .= ' '.trim($line);
            } else {
                $paragraph[] = trim($line);
            }
        }

        return [
            'version' => (int) ($version[1] ?? 1),
            'reviewed' => ($reviewed[1] ?? 'false') === 'true',
            'language' => $language,
            'title' => $documentTitle,
            'description' => isset($description[1]) ? trim($description[1]) : null,
            'song' => $song[1] ?? null,
            'blocks' => $blocks,
        ];
    }
}
