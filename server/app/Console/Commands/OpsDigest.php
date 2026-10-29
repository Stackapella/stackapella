<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Boards\Boards;
use App\Actions\Support\SupportInbox;
use App\Enums\SecurityEventType;
use App\Enums\SubmissionStatus;
use App\Models\BetaFeedback;
use App\Models\BetaSubmission;
use App\Models\CoachApplication;
use App\Models\EngagementReport;
use App\Models\EsConnection;
use App\Models\ExercisePack;
use App\Models\Harmony;
use App\Models\InvoiceNinjaRecord;
use App\Models\Notice;
use App\Models\Report;
use App\Models\SecurityEvent;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Ops\Check;
use App\Support\Ops\Readiness;
use App\Support\Stats\NetworkReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Each morning (docs/trust-and-safety-plan.md, "the daily digest"): yesterday in numbers, what's
 * waiting for Hillel, and anything not working. One email, nothing waiting more than a day.
 */
final class OpsDigest extends Command
{
    protected $signature = 'app:digest {--print : Show it instead of emailing it}';

    protected $description = 'Email the admins the daily digest';

    public function handle(Readiness $readiness, Admins $admins): int
    {
        $since = now()->subDay();
        $lines = [
            __('ops.digest.yesterday', [
                'signups' => SecurityEvent::query()->where('type', SecurityEventType::SignUp)->where('created_at', '>', $since)->count(),
                'plus' => User::query()->where('plus_paid_at', '>', $since)->count(),
                'shared' => Harmony::query()->whereNotNull('ready_at')->where('ready_at', '>', $since)->count(),
            ]),
        ];

        $waiting = array_filter([
            'support' => SupportInbox::overdue(),
            'appeals' => Notice::query()->whereNotNull('appealed_at')->whereNull('decided_at')->count(),
            'reports' => Report::query()->where('status', Report::OPEN)->count(),
            'applications' => CoachApplication::query()->where('status', CoachApplication::SUBMITTED)->count(),
            'packs' => ExercisePack::query()->where('status', ExercisePack::SUBMITTED)->count(),
            'coaching_reports' => EngagementReport::query()->whereNull('resolved_at')->count(),
            'submissions' => BetaSubmission::query()->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::InReview->value])->count(),
            'feedback' => BetaFeedback::query()->whereNull('read_at')->count(),
            'posts' => SocialPost::query()->where('status', SocialPost::AWAITING)->count(),
            'boards' => Boards::waiting()->count(),
        ]);

        foreach ($waiting as $what => $count) {
            $lines[] = __('ops.digest.waiting.'.$what, ['count' => $count]);
        }

        $network = NetworkReadiness::changes();

        foreach ($network as $change) {
            $lines[] = $change;
        }

        $failing = array_filter($readiness->run(), static fn (Check $check): bool => $check->status !== Check::PASS);

        foreach ($failing as $check) {
            $lines[] = __('ops.digest.check', ['status' => strtoupper($check->status), 'label' => $check->label, 'message' => $check->message]);
        }

        $failedJobs = DB::table('failed_jobs')->where('failed_at', '>', $since)->count();
        $reconnect = EsConnection::query()->where('status', EsConnection::NEEDS_RECONNECT)->count();
        $books = InvoiceNinjaRecord::query()->where('status', InvoiceNinjaRecord::FAILED)->count();

        foreach (array_filter(['failed_jobs' => $failedJobs, 'reconnect' => $reconnect, 'books' => $books]) as $what => $count) {
            $lines[] = __('ops.digest.trouble.'.$what, ['count' => $count]);
        }

        if (count($lines) === 1) {
            $lines[] = __('ops.digest.all_clear');
        }

        if ($this->option('print')) {
            foreach ($lines as $line) {
                $this->line($line);
            }

            return self::SUCCESS;
        }

        if (($to = $admins->all())->isNotEmpty()) {
            Notification::send($to, new OpsAlert(__('ops.digest.subject', ['date' => now()->subDay()->format('j M')]), __('ops.digest.title'), $lines));
        }

        // Said once: only after it's gone (a --print preview keeps them).
        NetworkReadiness::forgetChanges(count($network));

        $this->info('Digest sent ('.count($lines).' lines).');

        return self::SUCCESS;
    }
}
