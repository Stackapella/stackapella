<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * `app:emails:render` writes every email to files with made-up people, and sends none.
 */
final class RenderEmailsTest extends TestCase
{
    public function test_every_email_is_rendered_to_files_and_none_is_sent(): void
    {
        $dir = storage_path('framework/testing/emails-'.uniqid());

        $command = $this->artisan('app:emails:render', ['dir' => $dir]);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutputToContain('Rendered 29 emails')->assertSuccessful()->run();

        // Each account email in the game skin too (19 of them); the admin and mudeo emails in one.
        $this->assertCount(19, File::glob($dir.'/*.game.html'));
        $this->assertCount(29 + 19, File::glob($dir.'/*.html'));
        $this->assertCount(29, File::glob($dir.'/*.txt'));
        $this->assertStringContainsString('Continue your game', (string) File::get($dir.'/04-sign-in-link.game.html'));
        $this->assertStringNotContainsString('Continue your game', (string) File::get($dir.'/04-sign-in-link.html'));
        $this->assertStringContainsString('Try Stackapella', (string) File::get($dir.'/00-mudeo-new.html'));
        $this->assertStringContainsString('## 28.', (string) File::get($dir.'/index.md'));
        // Through the array mailer only: nothing left this machine.
        $this->assertSame('array', config('mail.default'));
        File::deleteDirectory($dir);
    }
}
