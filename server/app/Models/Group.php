<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A choir group (contract/api.md, "Choir groups"): a director with Plus, members who join by its
 * one link, the songs shared with it and who sings which part.
 *
 * @property int $id
 * @property int $director_id
 * @property string $name
 * @property string|null $link_hash
 * @property string|null $link_token the join link's token, for the director to copy again
 * @property Carbon|null $link_created_at
 * @property int|null $es_connection_id
 * @property string|null $es_schedule
 * @property string|null $es_schedule_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $director
 * @property-read Collection<int, GroupMember> $members
 * @property-read Collection<int, GroupSong> $songs
 * @property-read EsConnection|null $esConnection
 */
#[UseFactory(GroupFactory::class)]
final class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'name',
    ];

    protected $hidden = ['link_token'];

    protected function casts(): array
    {
        return [
            'link_created_at' => 'datetime',
            'link_token' => 'encrypted',
        ];
    }

    /**
     * The join link's address, while the token is kept (links made before it was, aren't).
     */
    public function linkUrl(): ?string
    {
        return $this->link_token !== null ? rtrim((string) config('app.url'), '/').'/choir/'.$this->link_token : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    /**
     * @return HasMany<GroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * @return HasMany<GroupSong, $this>
     */
    public function songs(): HasMany
    {
        return $this->hasMany(GroupSong::class);
    }

    /**
     * @return BelongsTo<EsConnection, $this>
     */
    public function esConnection(): BelongsTo
    {
        return $this->belongsTo(EsConnection::class, 'es_connection_id');
    }
}
