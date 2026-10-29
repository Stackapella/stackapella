<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Enums\SubmissionStatus;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A beta singer, for Hillel (contract/schemas/admin.beta.singer.json). Needs
 * assignments.template, assignments.latestSubmission and assignments.submissions loaded
 * (BetaSingerResource::RELATIONS).
 *
 * @property BetaSinger $resource
 */
final class BetaSingerResource extends JsonResource
{
    public const RELATIONS = ['user', 'assignments.template', 'assignments.clip', 'assignments.latestSubmission', 'assignments.submissions.guide'];

    /**
     * RELATIONS without the takes' outlines, which the list never shows (one per take, large).
     *
     * @return array<int|string, mixed>
     */
    public static function relationsForList(): array
    {
        $withoutPeaks = static fn ($query) => $query->select(array_values(array_diff((new BetaSubmission)->getConnection()->getSchemaBuilder()->getColumnListing('beta_submissions'), ['peaks'])));

        return ['user', 'assignments.template', 'assignments.clip', 'assignments.latestSubmission', 'assignments.submissions' => $withoutPeaks, 'assignments.submissions.guide'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(BetaSinger $singer): array
    {
        [$owed, $paid] = self::money($singer);

        return [
            'id' => $singer->sqid(),
            'name' => $singer->name,
            'email' => $singer->email,
            'note' => $singer->note,
            'link' => match (true) {
                ! $singer->isLive() => 'revoked',
                $singer->user_id !== null => 'accepted',
                $singer->inviteOpen() => 'active',
                default => 'expired',
            },
            'expiresAt' => $singer->expires_at?->utc()->toIso8601ZuluString(),
            'consent' => [
                'version' => $singer->consent_version,
                'acceptedAt' => $singer->consented_at?->utc()->toIso8601ZuluString(),
                'current' => $singer->hasConsented((int) config('beta.agreement_version')),
            ],
            'credit' => ['mode' => $singer->credit_mode?->value, 'name' => $singer->credit()],
            'account' => $singer->user !== null ? ['id' => $singer->user->sqid(), 'email' => $singer->user->email] : null,
            'acceptedAt' => $singer->accepted_at?->utc()->toIso8601ZuluString(),
            'hourlyRateCents' => $singer->hourly_rate_cents,
            'currency' => $singer->currency,
            'owedCents' => $owed,
            'paidCents' => $paid,
            'lastSeenAt' => $singer->last_seen_at?->utc()->toIso8601ZuluString(),
            'createdAt' => $singer->created_at?->utc()->toIso8601ZuluString(),
            'stats' => self::stats($singer),
            'assignments' => $singer->assignments->sortByDesc('id')->map(static fn (BetaAssignment $a): array => BetaAssignmentResource::fields($a))->values()->all(),
        ];
    }

    /**
     * Who did well, at a glance (the first beta: about 10 singers, the best hired for more): their
     * takes, how many were accepted or asked again, the versions published and how many lead,
     * and their takes' own pitch score.
     *
     * @return array{takes: int, accepted: int, retakes: int, published: int, defaults: int, pitchPercent: int|null}
     */
    private static function stats(BetaSinger $singer): array
    {
        $submissions = $singer->assignments->flatMap(static fn (BetaAssignment $a) => $a->submissions);
        $pitch = $submissions->map(static fn ($s): mixed => $s->scores['pitchPercent'] ?? null)->filter(static fn (mixed $p): bool => is_numeric($p));

        return [
            'takes' => $submissions->count(),
            'accepted' => $submissions->filter(static fn ($s): bool => $s->status === SubmissionStatus::Accepted)->count(),
            'retakes' => $submissions->where('status', SubmissionStatus::Retake)->count(),
            'published' => $submissions->filter(static fn ($s): bool => $s->guide !== null)->count(),
            'defaults' => $submissions->filter(static fn ($s): bool => $s->guide?->is_default === true)->count(),
            'pitchPercent' => $pitch->isEmpty() ? null : (int) round((float) $pitch->avg()),
        ];
    }

    /**
     * What's owed and paid, in cents: each part once, at the amount fixed when it was accepted.
     *
     * @return array{0: int, 1: int}
     */
    private static function money(BetaSinger $singer): array
    {
        $owed = 0;
        $paid = 0;

        foreach ($singer->assignments as $assignment) {
            foreach ($assignment->submissions as $submission) {
                /** @var BetaSubmission $submission */
                if ($submission->status !== SubmissionStatus::Accepted || $submission->payable_cents === 0) {
                    continue;
                }

                $submission->paid_at === null ? $owed += $submission->payable_cents : $paid += $submission->payable_cents;
            }
        }

        return [$owed, $paid];
    }
}
