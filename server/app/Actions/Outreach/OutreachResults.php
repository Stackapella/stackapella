<?php

declare(strict_types=1);

namespace App\Actions\Outreach;

use App\Models\OutreachTarget;
use App\Support\Outreach\Outreach;

/**
 * How outreach is going (docs/outreach-plan.md, slice 4), by group and by angle: organizations,
 * emailed, bounced, replied, a yes, the response rate, and what their ?ref= links brought (the
 * anonymous counts, folded under five arrivals). About organizations, never a person. Since
 * 2026-11-05 an email links to the plain domain (Hillel: "no extra tracking"), so only the
 * organizations whose sent email still carried a tag can be counted: `tracked` says how many, and a
 * row with none reads "not tracked" rather than a 0 that looks like a failure.
 */
final class OutreachResults
{
    /**
     * @return array{byGroup: list<array<string, mixed>>, byAngle: list<array<string, mixed>>}
     */
    public function handle(): array
    {
        $targets = OutreachTarget::query()->withCount([
            'messages as sent_count' => fn ($q) => $q->whereNotNull('sent_at'),
            'messages as bounced_count' => fn ($q) => $q->whereNotNull('bounced_at'),
            // A sent email that carried an "o-" tag (the sends before the plain links).
            'messages as tagged_count' => fn ($q) => $q->whereNotNull('sent_at')->where('body', 'like', '%ref='.Outreach::REF_PREFIX.'%'),
            'replies',
        ])->withSum('partnerCodes as redemptions', 'used')->get(['id', 'ref', 'group', 'angle', 'outcome']);
        $counts = Outreach::results(array_values($targets->pluck('ref')->map(static fn (mixed $r): string => (string) $r)->all()));

        $row = static function (string $key, iterable $group) use ($counts): array {
            $emailed = $replied = $bounced = $yes = $orgs = $partners = $redemptions = $tracked = 0;
            $brought = ['arrivals' => 0, 'lesson1' => 0, 'signedUp' => 0, 'schoolCodes' => 0];

            foreach ($group as $target) {
                $orgs++;
                $emailed += (int) ($target->getAttribute('sent_count') > 0);
                $bounced += (int) ($target->getAttribute('bounced_count') > 0);
                $tracked += (int) ($target->getAttribute('tagged_count') > 0);
                $replied += (int) ($target->getAttribute('replies_count') > 0);
                $yes += (int) in_array($target->outcome, OutreachTarget::POSITIVE, true);
                // Partners' codes redeemed: seats used, as the partner sees them.
                $partners += (int) ($target->getAttribute('redemptions') !== null);
                $redemptions += (int) $target->getAttribute('redemptions');

                foreach ($brought as $column => $n) {
                    $brought[$column] = $n + (int) ($counts[$target->ref][$column] ?? 0);
                }
            }

            // Summed only from organizations past the fold, so a group's totals never unfold one.
            return ['key' => $key, 'organizations' => $orgs, 'emailed' => $emailed, 'bounced' => $bounced, 'replied' => $replied, 'positive' => $yes,
                'responseRate' => $emailed > 0 ? round($replied / $emailed, 3) : null, 'tracked' => $tracked, ...$brought, 'partners' => $partners, 'redemptions' => $redemptions];
        };

        $by = static fn (string $field): array => array_values($targets->groupBy($field)->sortKeys()->map(static fn ($group, $key): array => $row((string) $key, $group))->all());

        return ['byGroup' => $by('group'), 'byAngle' => $by('angle')];
    }
}
