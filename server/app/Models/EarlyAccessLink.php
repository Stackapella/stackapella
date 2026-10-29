<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EarlyAccessLinkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A secret link that lets people make an account while sign-ups are paused (the mudeo email,
 * docs/mudeo-transition-plan.md): one open at a time per label, capped, pausable, and replaced
 * with a new code when it leaks (a replaced one stays, closed, for the counts).
 *
 * @property int $id
 * @property string $label
 * @property string $code_hash
 * @property string $code
 * @property int $cap
 * @property int $joined
 * @property Carbon|null $paused_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(EarlyAccessLinkFactory::class)]
final class EarlyAccessLink extends Model
{
    /** @use HasFactory<EarlyAccessLinkFactory> */
    use HasFactory;

    public const MUDEO = 'mudeo';

    /** Hillel's preview link: the app before launch, with no account (no sign-ups through it). */
    public const PREVIEW = 'preview';

    public const PREVIEW_CAP = 100000;

    /** Hillel's shared invite link: anyone holding it may make an account while sign-ups are
     * paused, and opens the app (never "from mudeo"). One link for many people, so its cap is
     * the preview's. */
    public const INVITE = 'invite';

    /** The links that let a sign-up through paused sign-ups. */
    public const SIGN_UP = [self::MUDEO, self::INVITE];

    public const DEFAULT_CAP = 500;

    protected function casts(): array
    {
        return [
            'code' => 'encrypted',
            'cap' => 'integer',
            'joined' => 'integer',
            'paused_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * open, paused, full or closed.
     */
    public function status(): string
    {
        return match (true) {
            $this->closed_at !== null => 'closed',
            $this->paused_at !== null => 'paused',
            $this->joined >= $this->cap => 'full',
            default => 'open',
        };
    }
}
