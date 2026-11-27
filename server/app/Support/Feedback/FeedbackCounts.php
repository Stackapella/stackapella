<?php

declare(strict_types=1);

namespace App\Support\Feedback;

use App\Models\SupportMessage;

/**
 * Feedback's daily counts (docs/feedback-plan.md, "What we'd measure"), anonymous: the app's fb_open:<from> (the form
 * opened, by where from) and fb_ask:<shown|right|off> (the choir ask), and the server's fb_sent:<topic> (a message
 * kept, by its topic, `none` when none was chosen).
 */
final class FeedbackCounts
{
    public const ASK = ['shown', 'right', 'off'];

    /** @return list<string> */
    public static function appKinds(): array
    {
        return [
            ...array_map(static fn (string $from): string => 'fb_open:'.$from, SupportMessage::MOMENTS),
            ...array_map(static fn (string $answer): string => 'fb_ask:'.$answer, self::ASK),
        ];
    }

    /** @return list<string> */
    public static function serverKinds(): array
    {
        return array_map(static fn (string $topic): string => 'fb_sent:'.$topic, [...SupportMessage::CATEGORIES, SupportMessage::BETA, 'none']);
    }

    public static function sent(?string $topic): string
    {
        return 'fb_sent:'.($topic ?? 'none');
    }
}
