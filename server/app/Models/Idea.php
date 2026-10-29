<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A writer's tune shared with every part open (docs/your-tune-plan.md, step 3): its notes are the
 * writer's template, and singers add their voices to it. On the Ideas shelf unless it waits for
 * review or Hillel hid it.
 *
 * @property int $id
 * @property string $public_id
 * @property int $template_id
 * @property int $user_id
 * @property string|null $note
 * @property int $voicing
 * @property list<string> $part_keys
 * @property Carbon $rights_confirmed_at
 * @property Carbon|null $held_at
 * @property Carbon|null $hidden_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $complete_told_at
 * @property Carbon|null $complete_emailed_at
 * @property Carbon|null $emailed_at
 * @property int $voices_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template $template
 * @property-read User $user
 */
#[UseFactory(IdeaFactory::class)]
final class Idea extends Model
{
    /** @use HasFactory<IdeaFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'voicing' => 'integer',
            'part_keys' => 'array',
            'rights_confirmed_at' => 'datetime',
            'held_at' => 'datetime',
            'hidden_at' => 'datetime',
            'completed_at' => 'datetime',
            'complete_told_at' => 'datetime',
            'complete_emailed_at' => 'datetime',
            'emailed_at' => 'datetime',
            'voices_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::creating(static function (Idea $idea): void {
            // Random, never guessable from the writer or the tune.
            $idea->public_id ??= Str::random(12);
        });
    }

    public function status(): string
    {
        return match (true) {
            $this->hidden_at !== null => 'hidden',
            $this->held_at !== null => 'held',
            default => 'listed',
        };
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<IdeaVoice, $this>
     */
    public function voices(): HasMany
    {
        return $this->hasMany(IdeaVoice::class);
    }
}
