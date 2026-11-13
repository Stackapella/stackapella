<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\CorrectionState;
use App\Enums\SubmissionStatus;
use App\Models\BetaFeedback;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Correction;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Ops\SitemapProblems;
use App\Support\Ops\SyncProblems;
use App\Support\Stats\Counts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The admin overview's numbers, from our own records (never analytics).
 */
final class Overview
{
    /**
     * @return array{beta: array<string, int>, harmonies: array{finished: int|null, secondSongs: int|null}}
     */
    public function numbers(): array
    {
        $parts = Template::query()->official()->with('currentVersion')->get()->sum(static fn (Template $t): int => count((array) ($t->currentVersion->data['parts'] ?? [])));

        return [
            'beta' => [
                'singers' => BetaSinger::query()->whereNull('forgotten_at')->count(),
                'consented' => BetaSinger::query()->whereNull('forgotten_at')->where('consent_version', (int) config('beta.agreement_version'))->count(),
                'waiting' => BetaSubmission::query()->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::InReview->value])->count(),
                'guides' => TemplateGuide::query()->count(),
                // Defaults are one per part (a unique index), so counting them counts the parts.
                'partsWithVoices' => TemplateGuide::query()->where('is_default', true)->whereIn('template_version_id', Template::query()->official()->whereNotNull('current_version_id')->select('current_version_id'))->count(),
                'parts' => (int) $parts,
                'unreadFeedback' => BetaFeedback::query()->whereNull('read_at')->count(),
            ],
            // Counted from our own records (docs/decisions.md, L).
            'harmonies' => Counts::songs(),
            'growth' => self::growth(),
            // Songs the last sync skipped, each still on its live version, for "What needs you now".
            'songsNotSynced' => SyncProblems::latest(),
            // What the weekly sitemap check found, the first few and how many more.
            'sitemapProblems' => SitemapProblems::brief(),
            // Readers' corrections still to read (Inbox > Corrections' badge, Home's row).
            'corrections' => ['new' => Correction::query()->where('state', CorrectionState::New->value)->count()],
        ];
    }

    /**
     * The last 7 days and the 7 before, leaving out the admins' own accounts.
     *
     * @return array{signUps: int, upgrades: int, activeSingers: int, previous: array{signUps: int, upgrades: int, activeSingers: int}}
     */
    private static function growth(): array
    {
        $admins = Counts::admins();
        $signUps = fn (Carbon $from, Carbon $to): int => User::query()->where('is_admin', false)->where('created_at', '>', $from)->where('created_at', '<=', $to)->count();
        $upgrades = fn (Carbon $from, Carbon $to): int => DB::table('subscriptions')->where('type', 'default')->whereIn('stripe_status', ['active', 'past_due'])
            ->whereNotIn('user_id', $admins)->where('created_at', '>', $from)->where('created_at', '<=', $to)->count();
        $now = now();
        $weekAgo = now()->subWeek();
        $twoWeeksAgo = now()->subWeeks(2);

        return [
            'signUps' => $signUps($weekAgo, $now),
            'upgrades' => $upgrades($weekAgo, $now),
            'activeSingers' => Counts::activeSingers(),
            // The 7 days before (8 to 14 days back), for the cards' change.
            'previous' => ['signUps' => $signUps($twoWeeksAgo, $weekAgo), 'upgrades' => $upgrades($twoWeeksAgo, $weekAgo), 'activeSingers' => Counts::activeSingers($twoWeeksAgo, $weekAgo)],
        ];
    }
}
