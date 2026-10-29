<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One part of a project: its settings, and which take it plays.
 *
 * @property int $id
 * @property string $uuid
 * @property int $project_id
 * @property int $order
 * @property string $name
 * @property string $color
 * @property string $volume
 * @property string $pan
 * @property bool $muted
 * @property bool $solo
 * @property int $nudge_frames
 * @property int $trim_start_frames
 * @property int $trim_end_frames
 * @property string|null $template_part_key
 * @property string $kind
 * @property int|null $current_take_id
 * @property array<string, mixed> $settings
 * @property int $version
 * @property string|null $last_change_id
 * @property Carbon|null $device_created_at
 * @property int $seq
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Take|null $currentTake
 * @property-read User|null $creator
 */
#[UseFactory(PartFactory::class)]
final class Part extends Model
{
    /** A voice or an instrument (docs/instrument-parts-plan.md), for the credits. */
    public const KINDS = ['voice', 'instrument'];

    /** @use HasFactory<PartFactory> */
    use HasFactory;

    protected $fillable = [
        'order',
        'name',
        'color',
        'volume',
        'pan',
        'muted',
        'solo',
        'nudge_frames',
        'trim_start_frames',
        'trim_end_frames',
        'template_part_key',
        'kind',
        'settings',
        'device_created_at',
    ];

    protected function casts(): array
    {
        return [
            'muted' => 'boolean',
            'solo' => 'boolean',
            'nudge_frames' => 'integer',
            'trim_start_frames' => 'integer',
            'trim_end_frames' => 'integer',
            'settings' => 'array',
            'device_created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Take, $this>
     */
    public function currentTake(): BelongsTo
    {
        return $this->belongsTo(Take::class, 'current_take_id');
    }

    /**
     * @return HasMany<Take, $this>
     */
    public function takes(): HasMany
    {
        return $this->hasMany(Take::class);
    }

    /**
     * Who made it, on a shared project (null: the project's owner).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
