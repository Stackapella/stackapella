<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\BlockType;
use App\Rules\BlockValue;
use App\Support\Trust\IpHash;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * admin.blocklist.create: a valid value of its type, not already on the list, and why.
 */
final class StoreBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = BlockType::tryFrom((string) $this->input('type'));

        $value = $this->input('value');

        if (! is_string($value)) {
            return;
        }

        // One spelling per value, so the same block can't be added twice in two spellings.
        $value = match ($type) {
            BlockType::Email, BlockType::EmailDomain => strtolower(trim($value)),
            BlockType::Ip => IpHash::canonical($value),
            BlockType::IpRange => self::network(trim($value)),
            default => trim($value),
        };

        $this->merge(['value' => $value]);
    }

    /**
     * A range written from any address in it (203.0.113.5/24) becomes its network (203.0.113.0/24).
     */
    private static function network(string $range): string
    {
        if (preg_match('#^([0-9a-f:.]+)/(\d{1,3})$#i', $range, $parts) !== 1 || ($packed = @inet_pton($parts[1])) === false) {
            return strtolower($range);
        }

        $bits = (int) $parts[2];
        $mask = '';
        for ($i = 0; $i < strlen($packed); $i++) {
            $keep = max(0, min(8, $bits - $i * 8));
            $mask .= chr($keep === 0 ? 0 : (0xFF << (8 - $keep)) & 0xFF);
        }

        return inet_ntop($packed & $mask).'/'.$bits;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $type = BlockType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(BlockType::class)],
            'value' => ['required', 'string', 'max:255', new BlockValue($type), Rule::unique('blocks', 'value')->where('type', $type?->value)],
            'reason' => ['required', 'string', 'max:500'],
            // ISO 8601 with a time zone, as every time in the contract.
            'expiresAt' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', 'date', 'after:now'],
        ];
    }
}
