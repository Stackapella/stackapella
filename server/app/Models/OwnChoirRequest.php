<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OwnChoirStatus;
use App\Models\Concerns\HasSqid;
use Database\Factories\OwnChoirRequestFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A song requested in a singer's own voice (track B): their clip (sent to `upload_key`, kept at `clip_key` only until
 * the song finishes), the part they sang along to and where beat 0 falls in it, the renderer's claim, and the parts.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $template_version_id null once its song is gone for good (the row kept, still counting)
 * @property int|null $own_choir_voice_id the kept voice it's made from (null once that voice is deleted)
 * @property bool $from_voice made from a kept voice rather than a new clip
 * @property string|null $part_key the clip's part: its template's melody
 * @property int|null $clip_template_version_id the template the clip sang (Happy Birthday by default); null: the song's own
 * @property int|null $offset_frames
 * @property int $clip_transpose the clip's tune moved to the singer's first note, in semitones
 * @property bool $guide false: the clip was sung with no headphones (2026-11-23)
 * @property array<string, mixed>|null $sound choir settings' mix (2026-11-23)
 * @property array<string, mixed>|null $sung how it's sung
 * @property bool $remake a remake: its parts replace its song's (parent_request_id) when made
 * @property string|null $failure_detail why its clip couldn't be used (length, quiet, loud, noisy, edge, short, notes)
 * @property string $voicing written, or low: the treble parts an octave down (2026-11-23)
 * @property int|null $octave the octave the app measured the clip sung in (−12, 0 or 12; a friend's tune)
 * @property OwnChoirStatus $status
 * @property string|null $failure
 * @property string|null $upload_key
 * @property string|null $clip_key
 * @property Carbon|null $clip_checked_at
 * @property int|null $sample_rate
 * @property int|null $frames
 * @property int|null $bytes
 * @property string|null $sha256
 * @property Carbon|null $claimed_at
 * @property string|null $claim_token
 * @property int $tries
 * @property float $part_minutes what it costs: each part's length in minutes, summed (OwnChoir::partMinutes)
 * @property float $gpu_seconds GPU time its claims spent, summed (each claim's largest report)
 * @property float $claim_gpu_seconds the current claim's largest report so far
 * @property bool $own the singer's own song from the creator
 * @property int $priority who's rendered first (1: paid Plus)
 * @property Carbon|null $starts_after a busy day's song waits until then
 * @property Carbon|null $claim_started_at when the current claim began (never kept past own_choir.claim_limit_minutes)
 * @property Carbon|null $first_claimed_at when it first reached the renderer (the windows count from it)
 * @property string|null $email_key
 * @property int|null $parent_request_id a friend's render of their parts on this owner's song (choir together)
 * @property int|null $own_choir_member_id the friend it's for
 * @property int|null $bill_to_user_id whose part-minutes it costs (the song's owner)
 * @property list<string>|null $only_parts the parts it renders (a friend's); null for every part
 * @property bool $held_for_minutes waiting for the owner's part-minutes the free song's email, as a keyed hash (OwnChoir::emailKey)
 * @property Carbon|null $ready_at
 * @property Carbon|null $seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read TemplateVersion|null $templateVersion
 * @property-read OwnChoirVoice|null $voice
 * @property-read Collection<int, OwnChoirPart> $parts
 */
#[UseFactory(OwnChoirRequestFactory::class)]
final class OwnChoirRequest extends Model
{
    /** @use HasFactory<OwnChoirRequestFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'part_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => OwnChoirStatus::class,
            'offset_frames' => 'integer',
            'from_voice' => 'boolean',
            'sample_rate' => 'integer',
            'frames' => 'integer',
            'bytes' => 'integer',
            'tries' => 'integer',
            'part_minutes' => 'float',
            'gpu_seconds' => 'float',
            'claim_gpu_seconds' => 'float',
            'own' => 'boolean',
            'priority' => 'integer',
            'starts_after' => 'datetime',
            'claim_started_at' => 'datetime',
            'first_claimed_at' => 'datetime',
            'only_parts' => 'array',
            'octave' => 'integer',
            'clip_transpose' => 'integer',
            'guide' => 'boolean',
            'sound' => 'array',
            'sung' => 'array',
            'remake' => 'boolean',
            'held_for_minutes' => 'boolean',
            'clip_checked_at' => 'datetime',
            'claimed_at' => 'datetime',
            'ready_at' => 'datetime',
            'seen_at' => 'datetime',
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
     * @return BelongsTo<TemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /** @return BelongsTo<TemplateVersion, $this> */
    public function clipTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'clip_template_version_id');
    }

    /** The template the clip sang: Happy Birthday by default, or (older requests, or by choice) the song's own. */
    public function clipVersion(): ?TemplateVersion
    {
        return $this->clipTemplateVersion ?? $this->templateVersion;
    }

    /**
     * @return BelongsTo<OwnChoirVoice, $this>
     */
    public function voice(): BelongsTo
    {
        return $this->belongsTo(OwnChoirVoice::class, 'own_choir_voice_id');
    }

    /**
     * Friends' renders of their parts on this song.
     *
     * @return HasMany<OwnChoirRequest, $this>
     */
    public function friendJobs(): HasMany
    {
        return $this->hasMany(OwnChoirRequest::class, 'parent_request_id');
    }

    /**
     * @return HasMany<OwnChoirMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OwnChoirMember::class);
    }

    /**
     * The friend a friend's job is for.
     *
     * @return BelongsTo<OwnChoirMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(OwnChoirMember::class, 'own_choir_member_id');
    }

    /**
     * @return BelongsTo<OwnChoirRequest, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OwnChoirRequest::class, 'parent_request_id');
    }

    /**
     * @return HasMany<OwnChoirPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(OwnChoirPart::class);
    }

    /** Claimed by the renderer, its claim still running, and (given) by the claim holding this token. */
    public function claimHeld(?string $token = null): bool
    {
        return $this->status === OwnChoirStatus::Rendering && $this->claimed_at !== null && $this->claimed_at->greaterThan(now()->subMinutes((int) config('own_choir.claim_minutes')))
            && ($token === null || ($this->claim_token !== null && hash_equals($this->claim_token, $token)));
    }
}
