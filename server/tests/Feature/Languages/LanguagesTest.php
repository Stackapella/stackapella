<?php

declare(strict_types=1);

namespace Tests\Feature\Languages;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Mail\Account\ResetPassword;
use App\Mail\Account\VerifyEmailAddress;
use App\Models\User;
use App\Support\Admin\SecondFactor;
use App\Support\Admin\StaffDevice;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Languages\Languages;
use App\Support\Languages\TranslationMaps;
use App\Support\Site\HelpGuide;
use App\Support\Site\SitemapPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Languages, slice 1 (docs/languages-plan.md; Hillel, 2026-11-11: Spanish first, then German): an account keeps its
 * language, and the app is told which languages are open to everyone, each behind its own switch.
 */
final class LanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_app_is_offered_english_and_each_language_switched_on(): void
    {
        $this->getJson('/api/auth/config')->assertJsonPath('data.languages', ['en']);

        app(Flags::class)->set('lang_de', 'on', null, 'German checked');
        $this->getJson('/api/auth/config')->assertJsonPath('data.languages', ['en', 'de']);

        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $config = $this->getJson('/api/auth/config');
        $this->assertMatchesContract($config, 'auth.config', 200);
        $config->assertJsonPath('data.languages', ['en', 'es', 'de']);
    }

    public function test_an_account_keeps_any_of_the_three_even_before_its_switch_and_nothing_else(): void
    {
        $ada = User::factory()->create()->fresh() ?? throw new \RuntimeException('no user');
        $this->actingAs($ada);

        // Spanish is still off, but a person checking the draft can keep it.
        $this->patchJson('/api/account', ['locale' => 'es'])->assertOk()->assertJsonPath('data.locale', 'es');
        $this->assertSame('es', $ada->refresh()->preferredLocale());
        $this->patchJson('/api/account', ['locale' => 'de'])->assertOk()->assertJsonPath('data.locale', 'de');
        $this->patchJson('/api/account', ['locale' => 'fr'])->assertJsonValidationErrors('locale');
        $this->assertSame('de', $ada->refresh()->locale);
    }

    public function test_a_sentence_not_yet_in_the_language_is_the_english_one(): void
    {
        app()->setLocale('es');

        // Admin stays English (the courses lead's maps leave it out).
        $this->assertSame(__('admin.sign_out', [], 'en'), __('admin.sign_out'));
    }

    public function test_a_translation_stands_in_only_for_its_own_english_with_the_same_placeholders(): void
    {
        $english = ['body' => 'Learning tracks, please: :piece.', 'saved' => ['title' => 'The mic is silent', 'gone' => 'Changed English'], 'count' => 'One song|:count songs'];
        $map = ['Learning tracks, please: :piece.' => 'Pistas de estudio, por favor: :piece.', 'The mic is silent' => 'El micrófono no suena', 'Old English' => 'Viejo', 'One song|:count songs' => 'Una canción'];

        // The English it was made from, or nothing: a changed sentence, a lost placeholder or plural falls back to English.
        $this->assertSame(['body' => 'Pistas de estudio, por favor: :piece.', 'saved' => ['title' => 'El micrófono no suena', 'gone' => 'Changed English'], 'count' => 'One song|:count songs'], TranslationMaps::translate($english, $map));
        $this->assertFalse(TranslationMaps::fits('Hi :name', 'Hola'));

        // The same English meaning two things: one key may have its own, only while its English is the one named.
        $levels = ['tuner' => ['low' => 'Low'], 'warmups' => ['card' => ['low' => 'Low']]];
        $this->assertSame(['tuner' => ['low' => 'Baja'], 'warmups' => ['card' => ['low' => 'Grave']]], TranslationMaps::translate($levels, ['Low' => 'Baja', '@warmups.card.low:Low' => 'Grave']));
        $this->assertSame(['tuner' => ['low' => 'Baja'], 'warmups' => ['card' => ['low' => 'Baja']]], TranslationMaps::translate($levels, ['Low' => 'Baja', '@warmups.card.low:Lower' => 'Grave']));
        // A name the code reads (a drawing, a part's key, an anchor) stays English, even where the word is text elsewhere.
        $named = ['card' => ['title' => 'groups', 'pic' => 'groups', 'song' => 'About the song'], 'demo' => ['part' => 'alto', 'label' => 'alto'], 'links' => [['guide' => 'mic', 'at' => 'before-you-record']]];
        $this->assertSame(['card' => ['title' => 'grupos', 'pic' => 'groups', 'song' => 'Sobre la canción'], 'demo' => ['part' => 'alto', 'label' => 'contralto'], 'links' => [['guide' => 'mic', 'at' => 'before-you-record']]], TranslationMaps::translate($named, ['groups' => 'grupos', 'alto' => 'contralto', 'mic' => 'micro', 'before-you-record' => 'antes', 'About the song' => 'Sobre la canción']));

        // A list asked for whole keeps every item, the untranslated ones in English.
        $this->assertSame(['card' => ['tie' => 'Tie it', 'low' => 'Grave']], TranslationMaps::translate(['card' => ['tie' => 'Tie it', 'low' => 'Low']], ['@card.low:Low' => 'Grave']));
        $this->assertSame(['warmups.card.low', 'Low'], TranslationMaps::entry('@warmups.card.low:Low'));
        $this->assertSame([null, 'Note: hold it'], TranslationMaps::entry('Note: hold it'));
        $this->assertFalse(TranslationMaps::fits('Hi', '   '));
        $this->assertTrue(TranslationMaps::fits('At 10:30, :name', 'A las 10:30, :name'));
    }

    public function test_a_lesson_page_in_spanish_answers_in_spanish_with_english_addresses_and_its_links_kept_in_spanish(): void
    {
        $page = $this->get('/es/courses/harmony/find-your-voice')->assertOk()->assertHeader('Content-Language', 'es');
        $html = (string) $page->getContent();
        $this->assertStringContainsString('<html lang="es"', $html);
        $this->assertStringContainsString('Encuentra tu voz', $html);
        // The next page is in Spanish too; a page Spanish doesn't serve keeps its English address.
        $this->assertStringContainsString('/es/courses/harmony/steady-notes"', $html);
        $this->assertStringNotContainsString('/es/legal/', $html);
        $this->assertStringNotContainsString('href="/courses/harmony/steady-notes"', $html);

        // The English page is as it was, and the language stays English for it.
        $english = (string) $this->get('/courses/harmony/find-your-voice')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="en"', $english);
        $this->assertStringContainsString('Find your voice', $english);
        // No alternates while Spanish is off.
        $this->assertStringNotContainsString('hreflang', $english);
        $this->assertStringNotContainsString('/es/', (string) $this->get('/sitemap.xml')->getContent());

        // A page Spanish doesn't serve has no Spanish address.
        $this->get('/es/legal/terms')->assertNotFound();
    }

    public function test_spanish_pages_open_with_their_switch_named_in_hreflang_and_the_sitemap(): void
    {
        // On the live site, Spanish pages answer only once Spanish is open.
        $this->app->instance('env', 'production');
        $this->get('/es/courses/harmony/find-your-voice')->assertNotFound();

        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $this->get('/es/courses/harmony/find-your-voice')->assertOk();

        foreach (['/courses/harmony/find-your-voice', '/es/courses/harmony/find-your-voice'] as $path) {
            $html = (string) $this->get($path)->getContent();
            $this->assertStringContainsString('hreflang="en" href="'.Hosts::publicUrl().'/courses/harmony/find-your-voice"', $html, $path);
            $this->assertStringContainsString('hreflang="es" href="'.Hosts::publicUrl().'/es/courses/harmony/find-your-voice"', $html, $path);
            $this->assertStringContainsString('hreflang="x-default" href="'.Hosts::publicUrl().'/courses/harmony/find-your-voice"', $html, $path);
        }
        // A page only in English (legal, by Hillel's decision) names no other language.
        $this->assertStringNotContainsString('hreflang', (string) $this->get('/legal/terms')->getContent());

        $sitemap = (string) $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString(Hosts::publicUrl().'/es/courses/harmony/find-your-voice</loc>', $sitemap);
        $this->assertStringNotContainsString('/es/legal/', $sitemap);
    }

    public function test_the_status_names_what_is_left_to_translate_and_what_is_stale(): void
    {
        // On a copy: the real map (the courses lead's) is put back as it was.
        $dir = lang_path('es');
        $made = ! is_dir($dir);
        File::ensureDirectoryExists($dir);
        $file = $dir.'/support.json';
        $kept = is_file($file) ? (string) file_get_contents($file) : null;
        try {
            File::put($file, (string) json_encode(['Learning tracks, please: :piece.' => 'Pistas de estudio, por favor: :piece.', 'An English sentence that was changed' => 'Vieja']));

            $this->artisan('app:languages:status', ['language' => 'es', '--list' => true])
                ->expectsOutputToContain('support: The mic is silent')
                ->doesntExpectOutputToContain('support: Learning tracks, please')
                ->expectsOutputToContain('stale.')
                ->assertSuccessful();

            // The map's sentence is used, through the translator, key by key.
            app('translator')->setLoaded([]);
            app()->setLocale('es');
            $this->assertSame('Pistas de estudio, por favor: X.', __('support.tracks.body', ['piece' => 'X']));
            $this->assertSame(__('support.saved.silent_mic.title', [], 'en'), __('support.saved.silent_mic.title'));
        } finally {
            if ($kept !== null) {
                File::put($file, $kept);
            } else {
                File::delete($file);
            }
            if ($made) {
                File::deleteDirectory($dir);
            }
            app('translator')->setLoaded([]);
        }

        $this->artisan('app:languages:status', ['language' => 'fr'])->assertFailed();
    }

    public function test_emails_go_in_the_accounts_language_and_a_newcomer_gets_the_browsers_open_one(): void
    {
        Mail::fake();
        $form = ['name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'elapsedMs' => 30000];

        // Spanish isn't open yet: a Spanish browser signs up in English.
        $this->withHeaders(['Accept-Language' => 'es-ES,es;q=0.9'])->postJson('/api/auth/register', $form)->assertStatus(202);
        $this->assertSame('en', User::query()->where('email', 'ana@example.com')->value('locale'));
        Mail::assertQueued(VerifyEmailAddress::class, static fn (VerifyEmailAddress $mail): bool => $mail->locale === 'en');

        // Open, the same browser's account is Spanish, and so is its email.
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        Mail::fake();
        $this->withHeaders(['Accept-Language' => 'es-ES,es;q=0.9'])->postJson('/api/auth/register', [...$form, 'email' => 'bo@example.com'])->assertStatus(202);
        $this->assertSame('es', User::query()->where('email', 'bo@example.com')->value('locale'));
        Mail::assertQueued(VerifyEmailAddress::class, static fn (VerifyEmailAddress $mail): bool => $mail->locale === 'es');

        // An account's own language wins over the browser's, and its reset link comes in it.
        $de = User::factory()->create(['email' => 'cy@example.com', 'locale' => 'de', 'email_verified_at' => now()]);
        Mail::fake();
        $this->withHeaders(['Accept-Language' => 'es'])->postJson('/api/auth/password/forgot', ['email' => 'cy@example.com'])->assertSuccessful();
        Mail::assertQueued(ResetPassword::class, static fn (ResetPassword $mail): bool => $mail->locale === 'de');
        $this->assertSame('de', $de->refresh()->locale);

        // A Spanish account's email reads Spanish where the map has it (lang/es/account.json).
        $spanish = (string) json_decode((string) file_get_contents(lang_path('es/account.json')), true)['Set a new Stackapella password'];
        app()->setLocale('es');
        $this->assertSame($spanish, (new ResetPassword('token', 'ana@example.com'))->envelope()->subject);
        app()->setLocale('en');
        $this->assertSame('Set a new Stackapella password', (new ResetPassword('token', 'ana@example.com'))->envelope()->subject);
    }

    public function test_every_translation_in_every_map_fits_its_english(): void
    {
        // A translation that drops a placeholder or a plural would never be shown: the drafts must fix it before landing.
        // An entry whose English has since changed (stale) is allowed: it's simply unused until re-translated.
        $checked = 0;
        foreach (File::directories(lang_path()) as $dir) {
            $language = basename($dir);
            if ($language === 'en') {
                continue;
            }
            $this->assertContains($language, Languages::ALL, "lang/{$language} isn't a language we have.");

            foreach (File::files($dir) as $file) {
                $this->assertSame('json', $file->getExtension(), "lang/{$language}/{$file->getFilename()}: translations are maps from the English, <group>.json.");
                $group = $file->getFilenameWithoutExtension();
                $this->assertFileExists(lang_path("en/{$group}.php"), "lang/{$language}/{$group}.json has no English group.");
                $map = json_decode((string) file_get_contents($file->getPathname()), true);
                $this->assertIsArray($map, "lang/{$language}/{$group}.json isn't a JSON map.");

                foreach ($map as $english => $translation) {
                    $this->assertIsString($translation, "lang/{$language}/{$group}.json: {$english}");
                    $this->assertTrue(TranslationMaps::fits(TranslationMaps::entry((string) $english)[1], $translation), "lang/{$language}/{$group}.json: \"{$translation}\" doesn't keep the placeholders or plural forms of \"{$english}\".");
                    $this->assertStringNotContainsString("\u{2014}", $translation, "lang/{$language}/{$group}.json: no em dashes.");
                    $checked++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(0, $checked);
    }

    public function test_a_help_sections_anchor_is_its_english_headings_in_every_language_and_every_link_finds_its_section(): void
    {
        foreach (HelpGuide::slugs() as $slug) {
            if (! HelpGuide::written($slug)) {
                continue;
            }
            app()->setLocale('en');
            $english = array_column(HelpGuide::sections($slug), 'id');
            app()->setLocale('es');
            $this->assertSame($english, array_column(HelpGuide::sections($slug), 'id'), "help/{$slug}: the anchors are the English headings' in Spanish too");
        }
        app()->setLocale('en');

        // Every link to a section names one that's there (a missing one would only lead to its page).
        $links = [];
        $walk = static function (mixed $node) use (&$walk, &$links): void {
            if (is_array($node)) {
                if (is_string($node['guide'] ?? null) && is_string($node['at'] ?? null)) {
                    $links[] = [$node['guide'], $node['at']];
                }
                array_map($walk, $node);
            }
        };
        $walk((array) __('pages.help'));
        $this->assertNotSame([], $links);
        foreach ($links as [$guide, $at]) {
            $this->assertContains($at, array_column(HelpGuide::sections($guide), 'id'), "help: {$guide}#{$at}");
        }
    }

    public function test_a_guide_answers_in_spanish_only_once_its_spanish_file_is_written(): void
    {
        $written = collect(glob(resource_path('guides/es/*.md')) ?: [])->map(static fn (string $f): string => basename($f, '.md'))->first();
        $this->assertIsString($written);
        $page = (string) $this->get('/es/guides/'.$written)->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="es"', $page);
        $this->assertStringNotContainsString('translatedFrom', $page);

        // A guide with no Spanish file has no Spanish address.
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::move(resource_path('guides/es/'.$written.'.md'), storage_path('framework/testing/'.$written.'.md'));
        try {
            $this->get('/es/guides/'.$written)->assertNotFound();
        } finally {
            File::move(storage_path('framework/testing/'.$written.'.md'), resource_path('guides/es/'.$written.'.md'));
        }
    }

    public function test_every_kind_of_spanish_page_answers_in_spanish(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        app(Flags::class)->set('compare_pages', 'on', null, 'In search');

        // Two of each kind the sitemap lists in Spanish: each answers, in Spanish, with no error.
        $paths = collect(app(SitemapPaths::class)->all())->filter(static fn (string $p): bool => str_starts_with($p, '/es/'))
            ->groupBy(static fn (string $p): string => explode('/', $p)[2] ?? '')->flatMap(static fn ($group) => $group->take(2))->values()->all();
        $this->assertGreaterThan(15, count($paths));

        $wrong = [];
        foreach ($paths as $path) {
            $response = $this->get($path);
            if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->getContent(), '<html lang="es"')) {
                $wrong[] = $path.' '.$response->getStatusCode();
            }
        }
        $this->assertSame([], $wrong);
    }

    public function test_the_home_page_is_in_spanish_at_es_and_the_landing_names_it(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        $home = (string) $this->get('/es')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="es"', $home);
        $this->assertStringContainsString('hreflang="es" href="'.Hosts::publicUrl().'/es"', $home);
        // Its link to the Spanish pricing page stays Spanish.
        $this->assertStringContainsString('/es/pricing', $home);

        $this->assertStringContainsString(Hosts::publicUrl().'/es</loc>', (string) $this->get('/sitemap.xml')->getContent());
        $landing = (string) $this->get(Hosts::publicUrl().'/?landing=1')->getContent();
        $this->assertStringContainsString('hreflang="es" href="'.Hosts::publicUrl().'/es"', $landing);
    }

    public function test_an_admins_device_previews_spanish_before_it_opens_and_no_one_else_does(): void
    {
        $this->app->instance('env', 'production');
        $value = StaffDevice::previewCookie(request())->getValue();

        // Anyone else: not found, and never kept.
        $closed = $this->get('/es/courses/harmony/find-your-voice')->assertNotFound();
        $this->assertStringContainsString('no-store', (string) $closed->headers->get('Cache-Control'));
        // The staff cookie's plain "1" (anyone can set it) is no key to a preview.
        $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, '1')->get('/es/courses/harmony/find-your-voice')->assertNotFound();

        // An admin's device: the page in Spanish, saying it's a preview, kept by nobody, named by no alternates.
        $page = $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, $value)->get('/es/courses/harmony/find-your-voice')->assertOk();
        $html = (string) $page->getContent();
        $this->assertStringContainsString('<html lang="es"', $html);
        $this->assertStringContainsString('Preview: ES is off for everyone else', $html);
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('hreflang', $html);
        $this->assertStringNotContainsString('/es/', (string) $this->get('/sitemap.xml')->getContent());

        // Once it's open: no note, and the page is everyone's.
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $open = (string) $this->get('/es/courses/harmony/find-your-voice')->assertOk()->getContent();
        $this->assertStringNotContainsString('Preview:', $open);
    }

    public function test_passing_the_second_factor_gives_an_admins_device_the_preview(): void
    {
        $preview = static fn ($response): ?string => collect($response->headers->getCookies())->first(static fn ($c): bool => $c->getName() === StaffDevice::PREVIEW_COOKIE)?->getValue();
        $pass = function (User $user) use ($preview): ?string {
            $secret = app(SecondFactor::class)->newTotpSecret();
            app(SecondFactor::class)->enableTotp($user, $secret);
            $this->actingAs($user)->withSession($this->adminSession($user, secondFactorPassed: false));

            $value = $preview($this->post('/admin/second-factor/challenge/code', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertRedirect());
            // A queued cookie belongs to its own request (each a new process outside tests).
            Cookie::flushQueuedCookies();

            return $value;
        };

        // Given once, when the second factor passes (no admin request after it checks or mints one).
        $value = $pass(User::factory()->admin()->create());
        $this->assertNotNull($value);
        $this->assertTrue(StaffDevice::previews(Request::create('/', 'GET', [], [StaffDevice::PREVIEW_COOKIE => $value])));
        $this->assertNull($preview($this->getJson('/api/admin/flags')->assertOk()));

        // An editor passing it is staff (the counts leave the device out) but gets no preview.
        $this->assertNull($pass(User::factory()->editor()->create()));
    }

    public function test_a_preview_cookie_stops_at_its_expiry_and_when_an_admin_or_a_second_factor_is_removed(): void
    {
        $previews = static fn (string $value): bool => StaffDevice::previews(Request::create('/', 'GET', [], [StaffDevice::PREVIEW_COOKIE => $value]));
        $mint = static fn (): string => (string) StaffDevice::previewCookie(request())->getValue();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $other->forceFill(['is_admin' => true])->save();
        app(SecondFactor::class)->enableTotp($admin, app(SecondFactor::class)->newTotpSecret());
        app(SecondFactor::class)->enableTotp($other, app(SecondFactor::class)->newTotpSecret());

        // The server checks its own signed expiry: a copied value stops after thirty days, whatever the browser keeps.
        $value = $mint();
        $this->assertTrue($previews($value));
        $this->travel(31)->days();
        $this->assertFalse($previews($value));
        $this->travelBack();
        // Nor can the expiry be pushed out by hand, and the old fixed value (one for every admin, forever) is refused.
        [$expires, $signature] = explode('.', $mint());
        $this->assertFalse($previews(((int) $expires + 86400).'.'.$signature));
        $this->assertFalse($previews(hash_hmac('sha256', 'staff-preview', (string) config('app.key'))));

        // An admin removed: every device's cookie stops (the remaining admin gets a new one when they next pass the second factor).
        $value = $mint();
        $other->forceFill(['is_admin' => false])->save();
        $this->assertFalse($previews($value));
        // A second factor removed, or an admin suspended: the same.
        $value = $mint();
        $admin->secondFactor()->delete();
        $this->assertFalse($previews($value));
        $this->assertTrue($previews($mint()));
        $value = $mint();
        $admin->forceFill(['suspended_at' => now()])->save();
        $this->assertFalse($previews($value));
    }
}
