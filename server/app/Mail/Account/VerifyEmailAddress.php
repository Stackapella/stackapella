<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\User;
use App\Support\Http\Hosts;
use App\Support\Mail\Bold;

/**
 * The verification link, sent when someone makes an account with a password (or asks again).
 */
final class VerifyEmailAddress extends AccountMail
{
    /** @param  list<string>  $doneLessons  the free course's lessons the device finished before it had an account */
    public function __construct(public readonly string $token, public readonly string $personName, public readonly array $doneLessons = []) {}

    protected function key(): string
    {
        return 'verify';
    }

    protected function url(): string
    {
        return Hosts::appLink('/verify?token='.$this->token);
    }

    /**
     * Hillel's words: the greeting and the address in one line, and no greeting of its own.
     */
    protected function name(): ?string
    {
        return null;
    }

    protected function greeting(): bool
    {
        return false;
    }

    /**
     * The free course's next lesson for this account: its first lesson without a star in its saved progress or among
     * the lessons the device finished signed out (a guest who did lesson 1 at the account wall is sent to lesson 2), linked with ?ref=welcome so Growth counts who came back from it (docs/retention-plan.md, row 7).
     *
     * @return array{line: string, button: string, url: string}|null
     */
    protected function next(): ?array
    {
        $user = User::query()->with('learning')->where('email', (string) $this->recipient())->first();
        $done = (array) ($user?->learning?->progress['lessons'] ?? []);
        $file = dirname(base_path()).'/web/course/course.json';
        $course = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $n = 0;

        foreach (is_array($course) ? (array) ($course['units'] ?? []) : [] as $unit) {
            foreach (is_array($unit) ? (array) ($unit['lessons'] ?? []) : [] as $lesson) {
                if (! is_array($lesson) || ! is_string($lesson['id'] ?? null)) {
                    continue;
                }

                $n++;

                if ((int) ($done[$lesson['id']]['stars'] ?? 0) > 0 || in_array($lesson['id'], $this->doneLessons, true)) {
                    continue;
                }

                $title = $lesson['title'] ?? '';
                $title = is_array($title) ? (string) ($title[app()->getLocale()] ?? $title['en'] ?? '') : (string) $title;

                return [
                    'line' => (string) __('account.mail.verify.next', ['n' => $n, 'title' => $title]),
                    'button' => (string) __('account.mail.verify.next_button'),
                    'url' => Hosts::appLink('/learn/'.$lesson['id'].'?ref=welcome'),
                ];
            }
        }

        return null;
    }

    protected function lines(): array
    {
        $words = ['name' => $this->personName, 'email' => (string) $this->recipient()];

        return [Bold::words($this->personName !== '' ? 'account.mail.verify.body' : 'account.mail.verify.body_plain', $words)];
    }
}
