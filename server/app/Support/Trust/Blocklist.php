<?php

declare(strict_types=1);

namespace App\Support\Trust;

use App\Enums\BlockType;
use App\Models\Block;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Is this email, IP or card blocked? Checked at sign-in (M1), and from later milestones at sign-up,
 * Checkout and the public forms (docs/trust-and-safety-plan.md, "The blocklist").
 */
final class Blocklist
{
    public function find(?string $email = null, ?string $ip = null, ?string $cardFingerprint = null): ?Block
    {
        $candidates = [];

        if ($email !== null && str_contains($email, '@')) {
            $email = Str::lower(trim($email));
            $candidates[BlockType::Email->value] = [$email];
            $candidates[BlockType::EmailDomain->value] = $this->domainsOf(Str::after($email, '@'));
        }

        if ($ip !== null) {
            $ip = IpHash::canonical($ip);
            $candidates[BlockType::Ip->value] = [$ip];
        }

        if ($cardFingerprint !== null) {
            $candidates[BlockType::CardFingerprint->value] = [$cardFingerprint];
        }

        if ($candidates === []) {
            return null;
        }

        $blocks = Block::query()->active()
            ->where(function ($query) use ($candidates, $ip): void {
                foreach ($candidates as $type => $values) {
                    $query->orWhere(fn ($q) => $q->where('type', $type)->whereIn('value', $values));
                }

                if ($ip !== null) {
                    $query->orWhere('type', BlockType::IpRange->value);

                    // An IPv6 address block covers its whole /64 (IpHash::subject).
                    if (str_contains($ip, ':')) {
                        $query->orWhere(fn ($q) => $q->where('type', BlockType::Ip->value)->where('value', 'like', '%:%'));
                    }
                }
            })
            ->get();

        foreach ($blocks as $block) {
            $matches = match ($block->type) {
                BlockType::IpRange => $ip !== null && IpUtils::checkIp($ip, $block->value),
                BlockType::Ip => $ip !== null && IpHash::subject($ip) === IpHash::subject($block->value),
                default => true,
            };

            if ($matches) {
                return $block;
            }
        }

        return null;
    }

    /**
     * A blocked domain covers its subdomains too: blocking spam.example blocks mail.spam.example.
     *
     * @return list<string>
     */
    private function domainsOf(string $domain): array
    {
        $parts = explode('.', $domain);
        $domains = [];

        while (count($parts) >= 2) {
            $domains[] = implode('.', $parts);
            array_shift($parts);
        }

        return $domains;
    }
}
