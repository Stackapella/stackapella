<?php

declare(strict_types=1);

namespace App\Support\Ops;

/**
 * One readiness check's result: pass, warn or fail, what it found, and the fix in one line.
 */
final readonly class Check
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public function __construct(public string $group, public string $key, public string $label, public string $status, public string $message, public string $fix = '') {}

    public static function pass(string $group, string $key, string $label, string $message): self
    {
        return new self($group, $key, $label, self::PASS, $message);
    }

    public static function when(bool $ok, string $group, string $key, string $label, string $ok_message, string $problem, string $fix, string $otherwise = self::FAIL): self
    {
        return $ok ? self::pass($group, $key, $label, $ok_message) : new self($group, $key, $label, $otherwise, $problem, $fix);
    }

    /**
     * @return array{group: string, key: string, label: string, status: string, message: string, fix: string}
     */
    public function toArray(): array
    {
        return ['group' => $this->group, 'key' => $this->key, 'label' => $this->label, 'status' => $this->status, 'message' => $this->message, 'fix' => $this->fix];
    }
}
