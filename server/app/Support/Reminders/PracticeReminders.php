<?php

declare(strict_types=1);

namespace App\Support\Reminders;

use App\Http\Requests\Billing\PushDeviceRequest;
use App\Models\PushDevice;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * The practice reminder (docs/operations-plan.md): at most one a day, on the days the person chose,
 * at their local time, by web push. The payload is never anyone's text (contract/schemas/
 * reminders.push.json): the service worker words it. A subscription the push service says has
 * gone (404, 410) is forgotten.
 */
final class PracticeReminders
{
    /** A reminder that couldn't go at its time (the server was down) is still sent within this. */
    private const LATE_MINUTES = 90;

    /**
     * Is a reminder due for this person now? A local time that doesn't exist (the clocks went
     * forward) falls to the next one that does; a repeated one is sent once (reminder_sent_on).
     */
    public static function due(User $user, Carbon $now): bool
    {
        if (! $user->reminder_enabled || $user->reminder_time === null || $user->reminder_timezone === null) {
            return false;
        }

        $local = $now->copy()->setTimezone($user->reminder_timezone);

        if (! in_array($local->dayOfWeek, $user->reminder_days ?? [], true) || $user->reminder_sent_on?->toDateString() === $local->toDateString()) {
            return false;
        }

        $at = Carbon::parse($local->toDateString().' '.$user->reminder_time, $user->reminder_timezone);

        return $local->gte($at) && $local->lt($at->copy()->addMinutes(self::LATE_MINUTES));
    }

    /**
     * @return int how many reminders went out
     */
    public function sendDue(Carbon $now): int
    {
        $public = (string) config('services.vapid.public_key');
        $private = (string) config('services.vapid.private_key');

        if ($public === '' || $private === '') {
            return 0;
        }

        $sent = 0;

        User::query()->where('reminder_enabled', true)->whereHas('pushDevices')->with('pushDevices')->chunkById(200, function ($users) use ($now, $public, $private, &$sent): void {
            foreach ($users as $user) {
                if (! self::due($user, $now)) {
                    continue;
                }

                $this->push($user, $public, $private);
                $user->forceFill(['reminder_sent_on' => $now->copy()->setTimezone((string) $user->reminder_timezone)->toDateString()])->save();
                $sent++;
            }
        });

        return $sent;
    }

    private function push(User $user, string $public, string $private): void
    {
        // Never redirected anywhere, and only to a browser's push service: checked when a device
        // was added, and again here (a device kept from before the list, or the list narrowed).
        $webPush = new WebPush(['VAPID' => ['subject' => (string) config('services.vapid.subject'), 'publicKey' => $public, 'privateKey' => $private]], [], new Client(['allow_redirects' => false, 'timeout' => 10]));
        $payload = (string) json_encode(['kind' => 'practice', 'url' => '/learn']);

        foreach ($user->pushDevices as $device) {
            if (! str_starts_with($device->endpoint, 'https://') || ! PushDeviceRequest::pushService($device->endpoint)) {
                $device->delete();

                continue;
            }

            $webPush->queueNotification(Subscription::create(['endpoint' => $device->endpoint, 'keys' => ['p256dh' => $device->p256dh, 'auth' => $device->auth]]), $payload);
        }

        try {
            foreach ($webPush->flush() as $report) {
                $device = $user->pushDevices->firstWhere('endpoint', $report->getEndpoint());

                if ($device instanceof PushDevice && $report->isSubscriptionExpired()) {
                    $device->delete();
                } elseif ($device instanceof PushDevice && $report->isSuccess()) {
                    $device->forceFill(['last_sent_at' => now()])->save();
                }
            }
        } catch (Throwable $e) {
            // A push service down for a moment: tomorrow's reminder still goes.
            report($e);
        }
    }
}
