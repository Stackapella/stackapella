<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\ProjectInviteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A link to sing on a project, sent by the owner themselves: only the token's hash is kept,
 * used once, valid 14 days, revocable.
 *
 * @property int $id
 * @property int $project_id
 * @property int $created_by
 * @property string $token_hash
 * @property string|null $part_key
 * @property string|null $part_name
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $used_by
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User $creator
 */
#[UseFactory(ProjectInviteFactory::class)]
final class ProjectInvite extends Model
{
    /** @use HasFactory<ProjectInviteFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'part_key',
        'part_name',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
