<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use App\Support\Legal\Terms;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

/**
 * A person with an account. In M1 these are the admins and editors; everyone signs in from M3.
 *
 * Server-owned fields (google_id, is_admin, is_editor, suspended_at) are not fillable: they are set
 * with forceFill() by the Action that owns them.
 *
 * @property int $id
 * @property string $name
 * @property string|null $handle
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $google_id
 * @property string|null $apple_id
 * @property string|null $avatar_key
 * @property string|null $bio
 * @property string|null $x_handle
 * @property string|null $bluesky_handle
 * @property string|null $timezone
 * @property array{types?: array<string, bool>, quietHours?: array{start: string, end: string}|null}|null $push_settings
 * @property Carbon|null $age_confirmed_at 13 or over: from the birth date, or on older accounts the old "13 or over" answer (legacy: no longer accepted, kept until they give a birth date)
 * @property string|null $ref
 * @property int|null $signup_harmony_id
 * @property Carbon|null $adult_confirmed_at
 * @property int|null $birth_month 1 to 12 (Age)
 * @property int|null $birth_year
 * @property string|null $country the latest country signed in from (Country)
 * @property Carbon|null $board_global_at when they chose to show on the global part leaderboards (18+)
 * @property Carbon|null $coach_active_at
 * @property string|null $heard_from
 * @property int|null $trust_level_override
 * @property string|null $trust_note
 * @property Carbon|null $trust_lowered_until
 * @property bool $is_admin
 * @property int|null $terms_version
 * @property Carbon|null $terms_accepted_at
 * @property bool $is_editor
 * @property string $locale
 * @property Carbon|null $suspended_at
 * @property string $plan
 * @property string|null $event_schedule_id
 * @property Carbon|null $plus_paid_until
 * @property string|null $pending_invite_hash
 * @property Carbon|null $trial_used_at
 * @property string|null $trial_subscription_id
 * @property Carbon|null $plus_renews_at
 * @property Carbon|null $past_due_until
 * @property Carbon|null $plus_paid_at
 * @property Carbon|null $plus_ended_at
 * @property string|null $plus_ended_reason
 * @property string|null $billing_notice
 * @property Carbon|null $billing_notice_at
 * @property list<string>|null $editable_project_ids
 * @property array{mode: string, colour: string|null, accent?: string|null, customAccent?: string|null, skin?: string}|null $appearance
 * @property int|null $early_access_link_id
 * @property bool $reminder_enabled
 * @property bool $idea_emails
 * @property string|null $apple_refresh_token
 * @property string|null $apple_web_refresh_token
 * @property string|null $store_account_token
 * @property Carbon|null $store_plus_until
 * @property Carbon|null $store_tester_at
 * @property list<int>|null $reminder_days
 * @property string|null $reminder_time
 * @property string|null $reminder_timezone
 * @property Carbon|null $reminder_sent_on
 * @property string|null $stripe_id
 * @property int $sync_seq
 * @property int $sync_pruned_seq
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AdminSecondFactor|null $secondFactor
 */
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable implements PasskeyUser
{
    /** The theme picker's choices (docs/decisions.md, "themes"). */
    public const APPEARANCE_MODES = ['system', 'light', 'dark'];

    public const APPEARANCE_COLOURS = ['warm', 'midnight', 'graphite', 'plum', 'stage'];

    /** The accent over the colour (Hillel's "the theme picker, refined"); null is the palette's own. */
    public const APPEARANCE_ACCENTS = ['coral', 'amber', 'green', 'teal', 'sky', 'indigo', 'violet', 'rose', 'custom'];

    /** Until a colour or accent is picked it's null: the mode's own (Stage in dark, Warm in light). */
    /** Game or serious (Hillel: "The entire system should work in either game mode or serious mode"; docs/skins-plan.md). */
    public const APPEARANCE_SKINS = ['game', 'serious'];

    public const APPEARANCE_DEFAULT = ['mode' => 'system', 'colour' => null, 'accent' => null, 'customAccent' => null, 'skin' => 'game'];

    use Billable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasSqid;
    use Notifiable;
    use PasskeyAuthenticatable;

    /**
     * What a new row has before it's saved, so a fresh model reads like a fetched one (strict
     * models refuse a missing attribute).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
        'is_editor' => false,
        'locale' => 'en',
        'suspended_at' => null,
        'plan' => 'free',
        'pending_invite_hash' => null,
        'trial_used_at' => null,
        'trial_subscription_id' => null,
        'plus_renews_at' => null,
        'past_due_until' => null,
        'plus_paid_at' => null,
        'plus_ended_at' => null,
        'plus_ended_reason' => null,
        'billing_notice' => null,
        'billing_notice_at' => null,
        'editable_project_ids' => null,
        'reminder_enabled' => false,
        'idea_emails' => true,
        'reminder_days' => null,
        'reminder_time' => null,
        'reminder_timezone' => null,
        'reminder_sent_on' => null,
        'stripe_id' => null,
        'sync_seq' => 0,
        'sync_pruned_seq' => 0,
    ];

    protected $fillable = [
        'name',
        'email',
        'locale',
        'bio',
        'timezone',
        'x_handle',
        'bluesky_handle',
        'heard_from',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'apple_refresh_token',
        'apple_web_refresh_token',
        'store_account_token',
        // Never in an answer (Age): only the person's own export has it.
        'birth_month',
        'birth_year',
    ];

    /**
     * The UUID the apps give the stores (Apple's appAccountToken, Google's obfuscatedAccountId),
     * so a store purchase names the account it was made for.
     */
    protected static function booted(): void
    {
        self::creating(static function (User $user): void {
            $user->store_account_token ??= (string) Str::uuid();
            // Every way of signing up says it's agreeing to the terms of use: the version it saw.
            $user->terms_version ??= Terms::current();
            $user->terms_accepted_at ??= now();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_editor' => 'boolean',
            'suspended_at' => 'datetime',
            'trial_used_at' => 'datetime',
            'plus_renews_at' => 'datetime',
            'past_due_until' => 'datetime',
            'plus_paid_at' => 'datetime',
            'plus_paid_until' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'plus_ended_at' => 'datetime',
            'billing_notice_at' => 'datetime',
            'editable_project_ids' => 'array',
            'appearance' => 'array',
            'push_settings' => 'array',
            'reminder_enabled' => 'boolean',
            'hide_profile' => 'boolean',
            'idea_emails' => 'boolean',
            'apple_refresh_token' => 'encrypted',
            'apple_web_refresh_token' => 'encrypted',
            'store_plus_until' => 'datetime',
            'store_tester_at' => 'datetime',
            'reminder_days' => 'array',
            'reminder_sent_on' => 'date',
            'age_confirmed_at' => 'datetime',
            'adult_confirmed_at' => 'datetime',
            'birth_month' => 'integer',
            'birth_year' => 'integer',
            'trust_level_override' => 'integer',
            'trust_lowered_until' => 'datetime',
        ];
    }

    /**
     * @return HasOne<AdminSecondFactor, $this>
     */
    public function secondFactor(): HasOne
    {
        return $this->hasOne(AdminSecondFactor::class);
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Mail goes in the person's own language (docs/architecture.md, "Languages").
     */
    public function preferredLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return HasMany<Harmony, $this>
     */
    public function harmonies(): HasMany
    {
        return $this->hasMany(Harmony::class);
    }

    /**
     * @return HasMany<Take, $this>
     */
    public function takes(): HasMany
    {
        return $this->hasMany(Take::class);
    }

    /**
     * Their synced course progress (account.learning), when they've saved any.
     *
     * @return HasOne<UserLearning, $this>
     */
    public function learning(): HasOne
    {
        return $this->hasOne(UserLearning::class);
    }

    /**
     * @return HasMany<PushDevice, $this>
     */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }
}
