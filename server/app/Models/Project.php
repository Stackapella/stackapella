<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A project saved to the cloud (docs/backend-plan.md C2), addressed by the UUID its device gave it.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $title
 * @property string $bpm
 * @property string|null $music_key
 * @property string $time_signature
 * @property string|null $template_slug
 * @property int|null $template_version
 * @property array<string, mixed> $settings
 * @property int $version
 * @property string|null $last_change_id
 * @property int $seq
 * @property Carbon|null $device_created_at
 * @property Carbon|null $binned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Part> $parts
 * @property-read Collection<int, ProjectMember> $members
 */
#[UseFactory(ProjectFactory::class)]
final class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'bpm',
        'music_key',
        'time_signature',
        'template_slug',
        'template_version',
        'settings',
        'device_created_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'device_created_at' => 'datetime',
            'binned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Take, $this>
     */
    public function takes(): HasMany
    {
        return $this->hasMany(Take::class);
    }

    /**
     * @return HasMany<Part, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class)->orderBy('order');
    }

    /**
     * The friends singing on it (the owner is user_id).
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }
}
