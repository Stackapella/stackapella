<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OutreachSuppressionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An address outreach never writes to again, forever: a "no thanks", an unsubscribe, a complaint, a
 * bounce or Hillel's say. Outreach's own list: a "no thanks" to an enquiry never stops an account's
 * sign-in emails (email_suppressions is the app's, and outreach checks both).
 *
 * @property int $id
 * @property string $email
 * @property string $reason no_thanks|unsubscribe|complaint|bounce|manual
 * @property int|null $outreach_target_id
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(OutreachSuppressionFactory::class)]
final class OutreachSuppression extends Model
{
    /** @use HasFactory<OutreachSuppressionFactory> */
    use HasFactory;

    public const REASONS = ['no_thanks', 'unsubscribe', 'complaint', 'bounce', 'manual'];

    protected $fillable = ['email', 'reason', 'note'];

    public static function blocks(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' && (self::query()->where('email', $email)->exists() || EmailSuppression::isSuppressed($email));
    }

    /**
     * Suppressed for good (the first reason stays: it's why).
     */
    public static function record(string $email, string $reason, ?int $targetId = null, ?string $note = null, ?int $by = null): void
    {
        $row = self::query()->firstOrNew(['email' => strtolower(trim($email))]);

        if (! $row->exists) {
            $row->forceFill(['reason' => $reason, 'outreach_target_id' => $targetId, 'note' => $note, 'created_by' => $by])->save();
        }
    }
}
