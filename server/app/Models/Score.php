<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ScoreFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A score summary in the history (contract/schemas/score.json): a take's report, or a scored
 * exercise. Numbers only, never audio.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string|null $take_uuid
 * @property string|null $template_slug
 * @property int|null $template_version
 * @property string|null $part_key
 * @property string|null $lesson
 * @property Carbon $recorded_at
 * @property array<string, mixed> $summary
 * @property Carbon|null $created_at
 */
#[UseFactory(ScoreFactory::class)]
final class Score extends Model
{
    /** @use HasFactory<ScoreFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'template_slug',
        'template_version',
        'part_key',
        'lesson',
        'recorded_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'summary' => 'array',
            'template_version' => 'integer',
        ];
    }
}
