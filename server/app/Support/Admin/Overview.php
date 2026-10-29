<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\SubmissionStatus;
use App\Models\BetaFeedback;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Stats\Counts;
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
            'growth' => [
                'signUps' => User::query()->where('created_at', '>', now()->subWeek())->count(),
                'upgrades' => DB::table('subscriptions')->where('type', 'default')->whereIn('stripe_status', ['active', 'past_due'])->where('created_at', '>', now()->subWeek())->count(),
                'activeSingers' => Counts::activeSingers(),
            ],
        ];
    }
}
