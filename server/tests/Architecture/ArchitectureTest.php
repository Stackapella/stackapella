<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * The Never and Always lists (CLAUDE.md, docs/conventions.md), checked by scanning the code, as
 * iaw does. A failure names the file and the rule.
 */
final class ArchitectureTest extends TestCase
{
    /**
     * @return list<SplFileInfo>
     */
    private function php(string $directory): array
    {
        return array_values(array_filter(File::allFiles(base_path($directory)), static fn (SplFileInfo $f): bool => $f->getExtension() === 'php'));
    }

    /**
     * @return list<SplFileInfo>
     */
    private function views(): array
    {
        return array_values(array_filter(File::allFiles(resource_path('views')), static fn (SplFileInfo $f): bool => str_ends_with($f->getFilename(), '.blade.php')));
    }

    private function withoutComments(string $code): string
    {
        $out = '';
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }

    public function test_every_php_file_is_strict_and_every_class_is_final(): void
    {
        foreach ([...$this->php('app'), ...$this->php('config'), ...$this->php('database'), ...$this->php('routes'), ...$this->php('tests'), ...$this->php('lang')] as $file) {
            $code = $file->getContents();
            $this->assertStringContainsString('declare(strict_types=1);', $code, "{$file->getRelativePathname()}: declare(strict_types=1) first.");
        }

        foreach ($this->php('app') as $file) {
            $code = $this->withoutComments($file->getContents());

            // Classes meant to be extended say so by being abstract, or are listed here with the reason.
            $extendable = ['Http/Middleware/EnsureAdmin.php' => 'EnsureEditor extends it', 'Http/Controllers/Controller.php' => 'the base controller'];

            if (preg_match('/^\s*class\s+\w+/m', $code) === 1 && ! isset($extendable[$file->getRelativePathname()])) {
                $this->fail("{$file->getRelativePathname()}: classes are final unless meant to be extended.");
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_env_is_only_read_in_config(): void
    {
        foreach ([...$this->php('app'), ...$this->php('routes'), ...$this->php('database'), ...$this->views()] as $file) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\w>:$])env\(/', $this->withoutComments($file->getContents()), "{$file->getRelativePathname()}: env() belongs in config/ only.");
        }
    }

    public function test_nothing_falls_back_to_sqlite(): void
    {
        $tests = array_filter($this->php('tests'), static fn (SplFileInfo $f): bool => ! str_starts_with($f->getRelativePathname(), 'Architecture/'));

        foreach ([...$this->php('config'), ...$this->php('database'), ...$tests] as $file) {
            $this->assertStringNotContainsString("'sqlite'", $this->withoutComments($file->getContents()), "{$file->getRelativePathname()}: MySQL only, tests included.");
        }

        $this->assertStringNotContainsString('sqlite', (string) file_get_contents(base_path('phpunit.xml')));
    }

    public function test_jobs_actions_and_services_never_touch_the_request(): void
    {
        foreach ([...$this->folder('app/Jobs'), ...$this->folder('app/Actions'), ...$this->folder('app/Services')] as $file) {
            $code = $this->withoutComments($file->getContents());
            $this->assertStringNotContainsString('request()', $code, "{$file->getRelativePathname()}: pass values in, never read request().");
            $this->assertStringNotContainsString('Illuminate\\Http\\Request', $code, "{$file->getRelativePathname()}: no Request in jobs, actions or services.");
        }
    }

    public function test_requests_are_never_mass_assigned(): void
    {
        foreach ($this->php('app') as $file) {
            $this->assertDoesNotMatchRegularExpression('/\$request->all\(\)|request\(\)->all\(\)|->fill\(\$request/', $this->withoutComments($file->getContents()), "{$file->getRelativePathname()}: use validated().");
        }
    }

    public function test_no_raw_http_outside_the_http_client(): void
    {
        foreach ($this->php('app') as $file) {
            $this->assertDoesNotMatchRegularExpression('/file_get_contents\(\s*[\'"]https?:|\bcurl_(init|exec|setopt)\(/', $this->withoutComments($file->getContents()), "{$file->getRelativePathname()}: use the Http facade.");
        }
    }

    public function test_catch_blocks_are_never_silently_empty(): void
    {
        foreach ($this->php('app') as $file) {
            // A catch with nothing at all in it. One that says why it ignores the error (a comment) is allowed.
            $this->assertDoesNotMatchRegularExpression('/catch\s*\([^)]*\)\s*\{\s*\}/', $file->getContents(), "{$file->getRelativePathname()}: report the error, or say in a comment why it's safe to ignore.");
        }
    }

    public function test_controllers_do_not_query_with_the_db_facade(): void
    {
        foreach ($this->php('app/Http/Controllers') as $file) {
            if ($file->getFilename() === 'HealthController.php') {
                continue; // Its `select 1` is the probe itself.
            }

            $this->assertDoesNotMatchRegularExpression('/DB::(table|select|statement|insert|update|delete)\(/', $this->withoutComments($file->getContents()), "{$file->getRelativePathname()}: queries belong in models or actions.");
        }

        foreach ($this->views() as $file) {
            $this->assertDoesNotMatchRegularExpression('/DB::|::query\(\)/', $file->getContents(), "{$file->getRelativePathname()}: no queries in views.");
        }
    }

    public function test_every_model_has_a_factory(): void
    {
        foreach (File::files(app_path('Models')) as $file) {
            $model = $file->getFilenameWithoutExtension();
            $this->assertFileExists(database_path("factories/{$model}Factory.php"), "{$model} needs a factory.");
            $this->assertStringContainsString('HasFactory', $file->getContents(), "{$model} needs the HasFactory trait.");
        }
    }

    public function test_every_enum_has_a_label(): void
    {
        foreach (File::files(app_path('Enums')) as $file) {
            $this->assertStringContainsString('public function label(): string', $file->getContents(), "{$file->getFilename()}: every enum has a label().");
        }
    }

    /**
     * No bulk or recurring email to users: each kind is about something the person did, one per
     * event. A new kind of email joins this list with who gets it.
     */
    private const EMAILS = [
        'Notifications/Admin/AdminSignedIn.php' => 'admins, when an admin opens admin',
        'Mail/Support/SupportAnswer.php' => 'the person who wrote to support, answering their own message',
        'Mail/Account/NoticeIssued.php' => 'a suspended person, with the statement of reasons they can\'t sign in to read',
        'Mail/Account/NoticeDecided.php' => 'someone who asked for a review of a statement of reasons, with the answer',
        'Notifications/Admin/OpsAlert.php' => 'admins only: a readiness change, an alert over its line, the daily digest',
        'Notifications/Admin/SecondFactorAdded.php' => 'admins, when an admin adds a factor',
        'Notifications/Admin/SecondFactorLocked.php' => 'admins, when an admin account locks',
        'Notifications/Admin/SecondFactorReset.php' => 'admins, when a factor is reset from the console',
        'Mail/Account/VerifyEmailAddress.php' => 'whoever signs up with a password, once (resend asks again)',
        'Mail/Account/SignInLinkMail.php' => 'whoever asks to sign in by email (an invite\'s "Accept"), each time they ask, within the address\'s limits',
        'Mail/Account/AlreadyHaveAnAccount.php' => 'an address that signs up again, instead of a second account',
        'Mail/Account/ResetPassword.php' => 'whoever asks to reset their password',
        'Mail/Account/ConfirmNewEmail.php' => 'the new address, when someone changes their email',
        'Mail/Account/EmailChanging.php' => 'the old address, when someone changes their email (with an undo link)',
        'Mail/Account/NewSignIn.php' => 'the account, on a sign-in from a device it has not seen',
        'Mail/Account/ExportReady.php' => 'whoever asked for their data, when it is ready',
        'Mail/Account/RecoveredBySupport.php' => 'the old address, when support helped its owner back in',
        'Mail/Account/TrialEnding.php' => 'a Plus subscriber, before their trial turns into a charge',
        'Mail/Account/PaymentFailed.php' => 'a Plus subscriber whose payment failed',
        // The one written exception (docs/decisions.md, "telling mudeo's users"): once, never again.
        'Mail/Ideas/IdeaVoicesMail.php' => 'an idea\'s writer, when people sing it: at most once a day per idea, with a setting and one-click unsubscribe (Hillel, 2026-10-27)',
        'Mail/Mudeo/MudeoInvite.php' => 'each of mudeo\'s old users, once, by Hillel\'s batch button (or the admin, as a test)',
    ];

    public function test_every_kind_of_email_is_on_the_list(): void
    {
        $found = [];

        foreach ($this->php('app') as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $reflection = class_exists($class) ? new ReflectionClass($class) : null;

            if ($reflection !== null && ! $reflection->isAbstract() && ($reflection->isSubclassOf(Mailable::class) || $reflection->isSubclassOf(Notification::class))) {
                $found[] = $file->getRelativePathname();
                $this->assertArrayHasKey($file->getRelativePathname(), self::EMAILS, "{$file->getRelativePathname()}: a new kind of email. No bulk or recurring email to users; add it to ArchitectureTest::EMAILS with who gets it.");
            }
        }

        $this->assertSame([], array_values(array_diff(array_keys(self::EMAILS), $found)), 'ArchitectureTest::EMAILS lists an email that no longer exists.');
    }

    public function test_routes_are_never_closures(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if (str_starts_with((string) $route->getName(), 'storage.')) {
                continue; // Laravel's own route for local private files (temporaryUrl() on a laptop).
            }

            $this->assertNotInstanceOf(\Closure::class, $route->getAction('uses'), "The route /{$route->uri()} is a closure.");
        }
    }

    public function test_every_route_is_named(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if ($route->isFallback) {
                continue;
            }

            $this->assertNotNull($route->getName(), "The route /{$route->uri()} needs a name.");
        }
    }

    public function test_admin_routes_need_sign_in_a_role_and_no_caching(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            $uri = $route->uri();

            // The way in and the way out work for anyone.
            if ((! str_starts_with($uri, 'admin') && ! str_starts_with($uri, 'api/admin')) || in_array($uri, ['admin/sign-in', 'admin/sign-out'], true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, "/{$uri} must require sign-in.");
            $this->assertTrue(in_array('admin', $middleware, true) || in_array('editor', $middleware, true), "/{$uri} must check the admin or editor role.");
            $this->assertTrue(in_array('no-store', $middleware, true) || in_array('api', $middleware, true), "/{$uri} must never be cached.");

            if (str_starts_with($uri, 'api/admin')) {
                $this->assertContains('second-factor', $middleware, "/{$uri} must need the second factor.");
            }
        }
    }

    public function test_routes_that_change_data_are_never_gets(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if (in_array('GET', $route->methods(), true)) {
                $this->assertDoesNotMatchRegularExpression('/@(store|update|destroy|create|delete|approve|reject|hide|confirm\w*|verify\w*|finish\w*)$/', (string) $route->getActionName(), "/{$route->uri()} changes data, so it must not be a GET.");
            }
        }
    }

    public function test_views_use_logical_direction_classes_only(): void
    {
        foreach ($this->views() as $file) {
            preg_match_all('/class="([^"]*)"/', $file->getContents(), $matches);

            foreach ($matches[1] as $classes) {
                $this->assertDoesNotMatchRegularExpression('/(^|\s)(\w+:)*-?(ml|mr|pl|pr|left|right|border-l|border-r|rounded-l|rounded-r|text-left|text-right)(-|\\s|$)/', $classes, "{$file->getRelativePathname()}: use ms-/me-/ps-/pe-/start-/end- (right-to-left text): {$classes}");
            }
        }
    }

    public function test_raw_output_is_limited_to_trusted_html(): void
    {
        $allowed = [
            'admin/auth/second-factor-setup.blade.php' => 1, // the QR code SVG we generate
        ];

        foreach ($this->views() as $file) {
            // A plain-text email is never HTML: its words are printed as they are.
            if (str_starts_with($file->getRelativePathname(), 'emails/') && str_ends_with($file->getFilename(), '-text.blade.php')) {
                continue;
            }

            $count = substr_count($file->getContents(), '{!!');
            $this->assertLessThanOrEqual($allowed[$file->getRelativePathname()] ?? 0, $count, "{$file->getRelativePathname()}: {!! !!} is only for trusted HTML we made; add it to the list with the reason.");
        }
    }

    public function test_no_em_dashes_in_words_people_read(): void
    {
        foreach ([...$this->php('lang'), ...$this->views(), ...$this->php('app')] as $file) {
            $this->assertStringNotContainsString("\u{2014}", $file->getContents(), "{$file->getRelativePathname()}: no em dashes.");
        }
    }

    public function test_no_npm_ever(): void
    {
        foreach ([base_path(), dirname(base_path()), dirname(base_path()).'/web'] as $directory) {
            $this->assertFileDoesNotExist($directory.'/package.json');
            $this->assertDirectoryDoesNotExist($directory.'/node_modules');
        }

        $this->assertFileDoesNotExist(base_path('vite.config.js'));
    }

    public function test_api_resources_send_values_not_sentences(): void
    {
        foreach ($this->folder('app/Http/Resources') as $file) {
            $this->assertDoesNotMatchRegularExpression('/\b(__|trans|trans_choice)\(/', $this->withoutComments($file->getContents()), "{$file->getRelativePathname()}: the API sends codes, and the app words them (docs/architecture.md, \"Languages\").");
        }
    }

    /**
     * @return list<SplFileInfo>
     */
    private function folder(string $directory): array
    {
        return is_dir(base_path($directory)) ? $this->php($directory) : [];
    }
}
