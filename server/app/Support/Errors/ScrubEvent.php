<?php

declare(strict_types=1);

namespace App\Support\Errors;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;

/**
 * What Sentry may see (docs/backend-plan.md A3): the error, where in our code, and which route,
 * never anything about a person or their singing. Requests lose their body, cookies, headers
 * (but the user agent) and query; no person at all, not even an id (Hillel: no IDs kept to
 * follow anyone, and Sentry is a third party); addresses, tokens and IPs in
 * messages and paths are masked; stack frames carry no arguments; breadcrumbs carry only their category and a masked message.
 */
final class ScrubEvent
{
    public static function event(Event $event, ?EventHint $hint = null): Event
    {
        $request = $event->getRequest();

        if ($request !== []) {
            $headers = (array) ($request['headers'] ?? []);
            $event->setRequest([
                'method' => $request['method'] ?? null,
                // Tokens in the path (a notice's, an invite's) are masked too.
                'url' => isset($request['url']) && is_string($request['url']) ? self::mask((string) strtok($request['url'], '?')) : null,
                'headers' => array_filter(['User-Agent' => $headers['User-Agent'] ?? $headers['user-agent'] ?? null]),
            ]);
        }

        $event->setUser(null);
        $event->setExtra([]);
        if ($event->getMessage() !== null) {
            $event->setMessage(self::mask($event->getMessage()));
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(self::mask($exception->getValue()));

            // A frame's arguments can be a person, an address or a message: never sent.
            foreach ($exception->getStacktrace()?->getFrames() ?? [] as $frame) {
                $frame->setVars([]);
            }
        }

        foreach ($event->getStacktrace()?->getFrames() ?? [] as $frame) {
            $frame->setVars([]);
        }

        return $event;
    }

    public static function breadcrumb(Breadcrumb $breadcrumb): Breadcrumb
    {
        return new Breadcrumb($breadcrumb->getLevel(), $breadcrumb->getType(), $breadcrumb->getCategory(), $breadcrumb->getMessage() !== null ? self::mask($breadcrumb->getMessage()) : null, [], $breadcrumb->getTimestamp());
    }

    /**
     * Addresses, bearer tokens, long secrets and IP addresses, masked.
     */
    public static function mask(string $text): string
    {
        return (string) preg_replace(
            ['/[\w.+-]+@[\w-]+\.[\w.-]+/u', '/\b(Bearer|token|secret|password|key)([=: ]+)\S+/i', '/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '/\b[0-9a-f]{0,4}(?::[0-9a-f]{0,4}){4,7}\b/i', '/\b[A-Za-z0-9_\-]{32,}\b/'],
            ['[email]', '$1$2[secret]', '[ip]', '[ip]', '[token]'],
            $text,
        );
    }
}
