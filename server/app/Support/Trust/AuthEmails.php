<?php

declare(strict_types=1);

namespace App\Support\Trust;

use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Flags;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Every account email bots can make us send (a verification, a sign-in link), counted across everyone each hour (the
 * CPU review, 2026-11-21): past ops.auth_emails_an_hour, shield mode turns on by itself (the forms then need
 * Turnstile) and the admins are told once. The email still goes; the switch stops the next ones.
 */
final class AuthEmails
{
    public static function sent(): void
    {
        $key = 'auth-emails:'.now()->format('YmdH');
        Cache::add($key, 0, 7200);
        $count = (int) Cache::increment($key);

        if ($count <= (int) config('ops.auth_emails_an_hour')) {
            return;
        }

        $flags = app(Flags::class);

        if (! $flags->on('shield_mode')) {
            $flags->set('shield_mode', 'on', null, "Automatic: {$count} sign-in and verification emails this hour");
        }

        if (Cache::add('auth-emails:told', true, 6 * 3600) && ($admins = app(Admins::class)->all())->isNotEmpty()) {
            Notification::send($admins, new OpsAlert(__('ops.auth_emails.subject'), __('ops.auth_emails.title'), [__('ops.auth_emails.line', ['count' => $count, 'limit' => (int) config('ops.auth_emails_an_hour')])]));
        }
    }
}
