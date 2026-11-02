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
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Support\Admin\Admins;
use App\Support\Ops\Check;
use App\Support\Ops\Readiness;
use App\Support\Stats\NetworkReadiness;
use App\Support\Stats\ReportDay;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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

    /** The setting holding the report day last sent: one digest a day, whatever runs. */
    public const SENT = 'ops.digest_sent_for';

    public function handle(Readiness $readiness, Admins $admins): int
    {
        // Yesterday in the report's timezone, as Admin's numbers count it.
        $day = ReportDay::now()->subDay()->toDateString();
        $print = (bool) $this->option('print');

        // Hourly: it goes at the first run from 07:00 (report time), once, so a missed hour is caught.
        if (! $print && (ReportDay::now()->hour < 7 || Setting::query()->where('key', self::SENT)->value('value') === $day)) {
            $this->line('Not due.');

            return self::SUCCESS;
        }

        [$from, $to] = [ReportDay::start($day), ReportDay::end($day)];
        $lines = [
            __('ops.digest.yesterday', [
                'signups' => SecurityEvent::query()->where('type', SecurityEventType::SignUp)->whereBetween('created_at', [$from, $to])->count(),
                'plus' => User::query()->whereBetween('plus_paid_at', [$from, $to])->count(),
                'shared' => Harmony::query()->whereNotNull('ready_at')->whereBetween('ready_at', [$from, $to])->count(),
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

        $failedJobs = DB::table('failed_jobs')->whereBetween('failed_at', [$from, $to])->count();
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

        Readiness::stamp(self::SENT, $day);

        if (($recipients = $admins->all())->isNotEmpty()) {
            Notification::send($recipients, new OpsAlert(__('ops.digest.subject', ['date' => Carbon::parse($day)->format('j M')]), __('ops.digest.title'), $lines));
        }

        // Said once: only after it's gone (a --print preview keeps them).
        NetworkReadiness::forgetChanges(count($network));

        $this->info('Digest sent ('.count($lines).' lines).');

        return self::SUCCESS;
    }
}
