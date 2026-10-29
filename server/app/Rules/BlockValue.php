<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\BlockType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * A blocklist value must be a valid one of its type: an email, a domain, an IP address, a CIDR
 * range or a Stripe card fingerprint.
 */
final class BlockValue implements ValidationRule
{
    public function __construct(private readonly ?BlockType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->type === null || ! is_string($value)) {
            return; // The type's own rule (or `string`) reports this.
        }

        $valid = match ($this->type) {
            BlockType::Email => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            BlockType::EmailDomain => preg_match('/^(?=.{3,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $value) === 1,
            BlockType::Ip => filter_var($value, FILTER_VALIDATE_IP) !== false,
            BlockType::IpRange => $this->isRange($value),
            BlockType::CardFingerprint => preg_match('/^[A-Za-z0-9_]{8,64}$/', $value) === 1,
        };

        if (! $valid) {
            $fail(__('validation.block_value.'.$this->type->value));
        }
    }

    private function isRange(string $value): bool
    {
        if (preg_match('#^([0-9a-f:.]+)/(\d{1,3})$#i', $value, $parts) !== 1 || filter_var($parts[1], FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $bits = str_contains($parts[1], ':') ? 128 : 32;

        // A range wider than a /8 (or /32 in IPv6) would block a good part of a country.
        return (int) $parts[2] <= $bits && (int) $parts[2] >= ($bits === 32 ? 8 : 32) && IpUtils::checkIp($parts[1], $value);
    }
}
