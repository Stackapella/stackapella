<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Users\SignInByLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * auth.link.send's work, after the answer: the request only checks and answers, so how long it
 * takes never tells whether the address has an account (SignInByLink::send).
 */
final class SendSignInLink implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly string $email, public readonly ?string $intended, public readonly ?string $invite, public readonly ?string $early, public readonly ?string $ip) {}

    public function handle(SignInByLink $links): void
    {
        $links->send($this->email, $this->intended, $this->invite, $this->early, $this->ip);
    }
}
