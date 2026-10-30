<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TemplateAccess;
use App\Models\Concerns\HasSqid;
use Carbon\CarbonInterface;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A song in the catalogue. Its notes live in its versions; this row holds what the catalogue
 * shows and who may start it.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property TemplateAccess $access
 * @property Carbon|null $free_from
 * @property Carbon|null $free_until
 * @property string|null $preview_key
 * @property int|null $current_version_id
 * @property int|null $owner_id
 * @property string|null $visibility
 * @property bool $library_opt_in
 * @property int|null $parent_id
 * @property array{song: string, source: string|null, ownWork: bool, at: string}|null $declaration
 * @property Carbon|null $published_at
 * @property Carbon|null $held_at
 * @property Carbon|null $hidden_at
 * @property Carbon|null $imported_at
 * @property Carbon|null $rights_confirmed_at
 * @property Carbon|null $featured_at
 * @property Carbon|null $idea_review_at
 * @property string|null $difficulty
 * @property list<string>|null $genres
 * @property string|null $music_key
 * @property int|null $tempo
 * @property list<int>|null $voicings
 * @property string|null $search_text
 * @property int $uses_count
 * @property int $ratings_count
 * @property int $ratings_sum
 * @property string $rating_score
 * @property Carbon|null $deleted_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TemplateVersion|null $currentVersion
 * @property-read User|null $owner
 * @property-read Template|null $parent
 */
#[UseFactory(TemplateFactory::class)]
final class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'slug',
        'title',
        'access',
        'free_from',
        'free_until',
    ];

    protected function casts(): array
    {
        return [
            'access' => TemplateAccess::class,
            'free_from' => 'date',
            'free_until' => 'date',
            'library_opt_in' => 'boolean',
            'declaration' => 'array',
            'published_at' => 'datetime',
            'held_at' => 'datetime',
            'hidden_at' => 'datetime',
            'imported_at' => 'datetime',
            'rights_confirmed_at' => 'datetime',
            'featured_at' => 'datetime',
            'idea_review_at' => 'datetime',
            'genres' => 'array',
            'voicings' => 'array',
            'deleted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    /**
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'current_version_id');
    }

    /**
     * The song of the month: free to everyone on this day.
     */
    public function isFreeOn(CarbonInterface $day): bool
    {
        return $this->free_from !== null && $this->free_until !== null
            && $day->toDateString() >= $this->free_from->toDateString()
            && $day->toDateString() <= $this->free_until->toDateString();
    }

    /**
     * Whose template it is: null for our own songs.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<TemplateRecording, $this>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(TemplateRecording::class);
    }

    public function isOfficial(): bool
    {
        return $this->owner_id === null;
    }

    /**
     * Published to the library (N3): listed, held for review, or hidden by Hillel.
     */
    /**
     * Made from sheet music the singer brought: private to them and their choir groups, never
     * public in any way (App\Support\Templates\Imported).
     */
    public function isImported(): bool
    {
        return ($this->getAttributes()['imported_at'] ?? null) !== null;
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function libraryStatus(): ?string
    {
        return match (true) {
            ! $this->isPublic() => null,
            $this->hidden_at !== null => 'hidden',
            $this->held_at !== null => 'held',
            default => 'listed',
        };
    }

    /**
     * The template it was remixed from.
     *
     * @return BelongsTo<Template, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Our own songs, never a person's template.
     *
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopeOfficial(Builder $query): Builder
    {
        return $query->whereNull('owner_id');
    }

    /**
     * The official songs anyone can find: not withdrawn (a song whose file left the courses repo
     * stays served to the projects made from it, by templates.show, but is listed nowhere).
     *
     * @param  Builder<Template>  $query
     * @return Builder<Template>
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query->whereNull('owner_id')->whereNull('withdrawn_at');
    }
}
