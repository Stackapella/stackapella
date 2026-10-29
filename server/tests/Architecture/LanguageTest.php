<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * English at launch, every language a drop-in (docs/architecture.md, "Languages"): every __() key
 * the server uses is in lang/en, and the Blade views carry no words of their own.
 */
final class LanguageTest extends TestCase
{
    /**
     * @return list<SplFileInfo>
     */
    private function sources(): array
    {
        return array_values(array_filter(
            [...File::allFiles(app_path()), ...File::allFiles(resource_path('views')), ...File::allFiles(base_path('routes'))],
            static fn (SplFileInfo $f): bool => $f->getExtension() === 'php',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function english(): array
    {
        $strings = [];

        foreach (File::files(lang_path('en')) as $file) {
            $strings[$file->getFilenameWithoutExtension()] = require $file->getPathname();
        }

        return $strings;
    }

    public function test_every_translation_key_used_is_in_lang_en(): void
    {
        $english = $this->english();
        $checked = 0;

        foreach ($this->sources() as $file) {
            preg_match_all('/\b(?:__|trans|trans_choice)\(\s*\'([a-z_]+\.[\w.\-]*)\'\s*([,)]|\.)/', $file->getContents(), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $key, $after]) {
                $checked++;

                if ($after === '.') {
                    // A key finished at runtime ('admin.mail.method.'.$method): the group must exist.
                    $this->assertIsArray(Arr::get($english, rtrim($key, '.')), "{$file->getRelativePathname()}: no group [{$key}] in lang/en.");

                    continue;
                }

                $this->assertIsString(Arr::get($english, $key), "{$file->getRelativePathname()}: the key [{$key}] isn't in lang/en.");
            }
        }

        $this->assertGreaterThan(20, $checked);
    }

    public function test_every_enum_value_has_a_label_in_lang_en(): void
    {
        foreach (File::files(app_path('Enums')) as $file) {
            $enum = 'App\\Enums\\'.$file->getFilenameWithoutExtension();

            foreach ($enum::cases() as $case) {
                $this->assertNotSame('', $case->label());
                $this->assertStringNotContainsString('enums.', $case->label(), "{$enum}::{$case->name} has no label in lang/en/enums.php.");
            }
        }
    }

    public function test_views_have_no_words_of_their_own(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            // The public pages are the frontend agent's, checked by its own tests.
            if (str_starts_with($file->getRelativePathname(), 'public/')) {
                continue;
            }

            $text = $file->getContents();
            $text = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $text);
            $text = (string) preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', $text);
            $text = (string) preg_replace('/<(script|style)\b.*?<\/\1>/s', '', $text);
            $text = (string) preg_replace('/@\w+\s*(\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/', '', $text);
            // A tag, its quoted attributes whole (one may hold a ">", as in `['n' => $i]`).
            $text = (string) preg_replace('/<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/s', '', $text);
            $text = html_entity_decode($text);

            $this->assertDoesNotMatchRegularExpression('/\p{L}{2,}/u', $text, "{$file->getRelativePathname()} has words of its own; put them in lang/en and use __(): ".trim(mb_strimwidth(preg_replace('/\s+/', ' ', $text) ?? '', 0, 200)));
        }
    }
}
