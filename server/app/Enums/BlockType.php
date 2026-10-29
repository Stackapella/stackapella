<?php

declare(strict_types=1);

namespace App\Enums;

enum BlockType: string
{
    case Email = 'email';
    case EmailDomain = 'email_domain';
    case Ip = 'ip';
    case IpRange = 'ip_range';
    case CardFingerprint = 'card_fingerprint';

    public function label(): string
    {
        return __('enums.block_type.'.$this->value);
    }
}
