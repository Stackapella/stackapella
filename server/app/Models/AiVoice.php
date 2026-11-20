<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\AiVoiceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An AI guide voice (docs/ai-voices-build-plan.md): its label name, the singer it was learned from (null for one not
 * based on a named person), their consent, and whether it's withdrawn. Its guides leave Admin only once consent is
 * recorded (consented()), and withdrawing it retires all of them (App\Actions\AiVoices\WithdrawAiVoice). Never deleted.
 *
 * @property int $id
 * @property int|null $beta_singer_id the beta singer who licensed it in the app (null: added by app:ai-voice:add)
 * @property string $name
 * @property string|null $based_on
 * @property Carbon|null $consent_date
 * @property string|null $consent_version
 * @property Carbon|null $accepted_at when they accepted the licence in the app (its version is consent_version)
 * @property bool $payment_noted
 * @property bool $credited the singer chose to be named on the label (else the plain AI label)
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BetaSinger|null $betaSinger
 */
#[UseFactory(AiVoiceFactory::class)]
final class AiVoice extends Model
{
    /** @use HasFactory<AiVoiceFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'name',
        'based_on',
    ];

    protected function casts(): array
    {
        return [
            'consent_date' => 'date',
            'payment_noted' => 'boolean',
            'credited' => 'boolean',
            'withdrawn_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->withdrawn_at === null;
    }

    /**
     * The name for "AI voice based on <name>'s singing, with permission": only when the singer chose to be credited and
     * the voice is consented and active; else null (the plain "AI voice, waiting for a real singer").
     */
    public function labelName(): ?string
    {
        return $this->credited && $this->isConsented() ? $this->based_on : null;
    }

    /** Consent recorded and not withdrawn: what it sings may leave Admin (with the ai_voices switch on). */
    public function isConsented(): bool
    {
        return $this->isActive() && $this->consent_date !== null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeConsented(Builder $query): Builder
    {
        return $query->whereNull('withdrawn_at')->whereNotNull('consent_date');
    }

    /**
     * @return BelongsTo<BetaSinger, $this>
     */
    public function betaSinger(): BelongsTo
    {
        return $this->belongsTo(BetaSinger::class);
    }

    /**
     * Its live AI guides (any version).
     *
     * @return HasMany<TemplateGuide, $this>
     */
    public function guides(): HasMany
    {
        return $this->hasMany(TemplateGuide::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<AiUpload, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(AiUpload::class);
    }
}
