<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\AppWords;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The site's pictures and guide name the app's own words (AppWords). A picture only draws with a
 * hero song, which the page tests don't have, so this reads every key the site names and checks the
 * app still has it: a word renamed in the app fails here, not as a page that won't open.
 */
final class AppWordsTest extends TestCase
{
    public function test_every_app_word_the_site_names_is_still_in_the_app(): void
    {
        $keys = [];
        foreach ([...File::allFiles(resource_path('views')), ...File::allFiles(app_path())] as $file) {
            preg_match_all("/AppWords::(?:get|fill|format)\('([^']+)'\s*[,)]/", $file->getContents(), $found);
            $keys = [...$keys, ...$found[1]];
        }

        $this->assertNotEmpty($keys);
        foreach (array_unique($keys) as $key) {
            $this->assertNotSame('', AppWords::get($key), $key);
        }
    }
}
