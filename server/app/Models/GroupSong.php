<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GroupSongFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A song shared with a choir group: one of ours, or the director's own template.
 *
 * @property int $id
 * @property int $group_id
 * @property int $template_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Template $template
 * @property-read Collection<int, GroupAssignment> $assignments
 */
#[UseFactory(GroupSongFactory::class)]
final class GroupSong extends Model
{
    /** @use HasFactory<GroupSongFactory> */
    use HasFactory;

    protected $fillable = [
        'group_id',
        'template_id',
    ];

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return HasMany<GroupAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(GroupAssignment::class);
    }
}
