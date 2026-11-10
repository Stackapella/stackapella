<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSqid;
use Database\Factories\OutreachTargetFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An organization Hillel may write to (docs/outreach-plan.md): who they are, why Stackapella fits,
 * their published contact and where it was published, the angle, and how it's going.
 *
 * @property int $id
 * @property string $ref
 * @property string $name
 * @property string $group
 * @property string|null $country
 * @property string|null $reach
 * @property string|null $fit
 * @property string|null $contact_email
 * @property string|null $contact_form_url
 * @property string|null $source_url
 * @property string $angle
 * @property Carbon|null $send_by after this day (the report's), the first email's wording may be stale
 * @property Carbon|null $follow_up_not_before its follow-up waits for this day (the report's)
 * @property Carbon|null $send_not_before the first email waits for this day (a busy week, spacing, a hold)
 * @property string|null $timing_note why its dates are what they are, and checks to make on the day
 * @property string $status
 * @property string|null $notes
 * @property string|null $research_note what marketing found about it, in plain words
 * @property list<string>|null $research_links the pages each specific came from
 * @property string|null $outcome
 * @property string|null $outcome_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(OutreachTargetFactory::class)]
final class OutreachTarget extends Model
{
    /** @use HasFactory<OutreachTargetFactory> */
    use HasFactory;

    use HasSqid;

    /** Stored statuses ("follow-up due" is worked out, never stored). */
    public const STATUSES = ['to_research', 'ready', 'drafted', 'sent', 'replied', 'partner', 'closed', 'do_not_contact'];

    /** The angles a draft can start from (lang/en/outreach.php). */
    public const ANGLES = ['school', 'choir_groups', 'course', 'practice_tool'];

    public const OUTCOMES = ['interested', 'joined', 'declined', 'no_reply', 'other'];

    /** Outcomes that count as a yes in the results. */
    public const POSITIVE = ['interested', 'joined'];

    protected $fillable = ['name', 'group', 'country', 'reach', 'fit', 'contact_email', 'contact_form_url', 'source_url', 'angle', 'notes', 'outcome_note', 'research_note', 'research_links', 'send_by', 'send_not_before', 'follow_up_not_before', 'timing_note'];

    protected function casts(): array
    {
        return ['research_links' => 'array', 'send_by' => 'date:Y-m-d', 'send_not_before' => 'date:Y-m-d', 'follow_up_not_before' => 'date:Y-m-d'];
    }

    /**
     * @return HasMany<OutreachMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class);
    }

    /**
     * Its partner codes (school codes of kind partner), once it's a partner.
     *
     * @return HasMany<SchoolClass, $this>
     */
    public function partnerCodes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * @return HasMany<OutreachReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(OutreachReply::class);
    }
}
