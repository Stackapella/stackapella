<?php

declare(strict_types=1);

namespace App\Services\Social;

/**
 * One network our own account posts to (docs/backend-plan.md D7, a port of iaw's).
 */
interface SocialClient
{
    /** Whether the account has credentials (nothing is proposed for a network without). */
    public function configured(): bool;

    /**
     * @return array{id: string, url: string}
     */
    public function post(SocialMessage $message): array;

    /** Takes a post of ours down again, by the id post() gave (already gone is done too). */
    public function delete(string $id): void;
}
