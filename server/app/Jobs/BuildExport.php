<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\Account\ExportReady;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CoachMessage;
use App\Models\CoachRating;
use App\Models\DataExport;
use App\Models\Engagement;
use App\Models\GroupMember;
use App\Models\Harmony;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ReviewItem;
use App\Models\SecurityEvent;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Http\Hosts;
use App\Support\Push\AppPushes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;
use Throwable;
use ZipArchive;

/**
 * Build one "Download my data" ZIP under exports/ in `private` (the bucket expires it after 7
 * days), then tell the person. Everything we hold about them (docs/legal privacy, "Your rights"):
 * the main files are shaped for reading, and records/ holds the rest, a file per kind. Each list
 * is written to a temporary file a few rows at a time, and a take's outline is never read, so a
 * heavy account fits in the job's memory.
 */
final class BuildExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** A very active account's export (thousands of links) can outlast the default worker's minute. */
    public int $timeout = 280;

    public function __construct(public readonly int $exportId)
    {
        $this->onQueue('long');
    }

    public function failed(?Throwable $e): void
    {
        DataExport::query()->whereKey($this->exportId)->where('status', 'preparing')->update(['status' => 'failed']);
    }

    public function handle(): void
    {
        $export = DataExport::query()->with('user')->find($this->exportId);

        if ($export === null || $export->status !== 'preparing') {
            return;
        }

        $user = $export->user;
        $path = (string) tempnam(sys_get_temp_dir(), 'export');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $zip->addFromString('account.json', (string) json_encode([
            'name' => $user->name,
            'handle' => $user->handle,
            'email' => $user->email,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'bio' => $user->bio,
            'xHandle' => $user->x_handle,
            'blueskyHandle' => $user->bluesky_handle,
            'signInMethods' => array_keys(array_filter(['google' => $user->google_id !== null, 'apple' => $user->apple_id !== null, 'event_schedule' => $user->event_schedule_id !== null, 'password' => $user->password !== null])),
            'heardFrom' => $user->heard_from,
            // The country they last signed in from (Cloudflare's), never an address.
            'country' => $user->country,
            'onGlobalBoard' => $user->board_global_at !== null,
            'ref' => $user->ref,
            'ageConfirmedAt' => $user->age_confirmed_at?->utc()->toIso8601ZuluString(),
            'adultConfirmedAt' => $user->adult_confirmed_at?->utc()->toIso8601ZuluString(),
            'birthMonth' => $user->birth_month,
            'birthYear' => $user->birth_year,
            'practiceReminders' => ['on' => (bool) $user->reminder_enabled, 'days' => $user->reminder_days, 'time' => $user->reminder_time, 'timezone' => $user->reminder_timezone],
            'appPushes' => AppPushes::settings($user),
            'appearance' => $user->appearance,
            'ideaEmails' => (bool) ($user->getAttributes()['idea_emails'] ?? true),
            'storePlusUntil' => $user->store_plus_until?->utc()->toIso8601ZuluString(),
            'emailVerifiedAt' => $user->email_verified_at?->utc()->toIso8601ZuluString(),
            'hideProfile' => (bool) ($user->getAttributes()['hide_profile'] ?? false),
            'publicProfileSince' => ($at = $user->getAttributes()['public_profile_at'] ?? null) !== null ? Carbon::parse($at)->utc()->toIso8601ZuluString() : null,
            'league' => ['tier' => $user->getAttributes()['league_tier'] ?? null, 'joinedAt' => ($at = $user->getAttributes()['league_joined_at'] ?? null) !== null ? Carbon::parse($at)->utc()->toIso8601ZuluString() : null],
            'plan' => $user->plan,
            'plus' => [
                'renewsAt' => $user->plus_renews_at?->utc()->toIso8601ZuluString(),
                'paidAt' => $user->plus_paid_at?->utc()->toIso8601ZuluString(),
                'endedAt' => $user->plus_ended_at?->utc()->toIso8601ZuluString(),
                'endedReason' => $user->plus_ended_reason,
                'pastDueUntil' => $user->past_due_until?->utc()->toIso8601ZuluString(),
                'card' => $user->pm_last_four !== null ? ['type' => $user->pm_type, 'lastFour' => $user->pm_last_four] : null,
            ],
            'coachSince' => ($at = $user->getAttributes()['coach_active_at'] ?? null) !== null ? Carbon::parse($at)->utc()->toIso8601ZuluString() : null,
            // The terms of use they agreed to, and when.
            'termsAccepted' => ['version' => $user->terms_version, 'at' => $user->terms_accepted_at?->utc()->toIso8601ZuluString()],
            'trialUsedAt' => $user->trial_used_at?->utc()->toIso8601ZuluString(),
            'joinedThroughEarlyAccess' => $user->early_access_link_id !== null,
            'plusPaidUntil' => $user->plus_paid_until?->utc()->toIso8601ZuluString(),
            'createdAt' => $user->created_at?->utc()->toIso8601ZuluString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // What we keep about their sign-ins, for abuse (raw IPs only for 30 days).
        $zip->addFromString('sign-ins.json', (string) json_encode(SecurityEvent::query()->where('user_id', $user->id)->orderBy('id')->get()->map(static fn (SecurityEvent $e): array => [
            'type' => $e->type->value,
            'ip' => $e->ip,
            'at' => $e->created_at->utc()->toIso8601ZuluString(),
        ])->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Every project, its parts, and each take as a link that works as long as the export (M4).
        $linksUntil = now()->addDays(DataExport::DAYS);
        $takeColumns = ['id', 'uuid', 'part_id', 'project_id', 'status', 'sample_rate', 'frames', 'file_key', 'binned_at', 'created_at'];
        $projects = Project::query()->where('user_id', $user->id)->lazyById(50)->map(static function (Project $project) use ($takeColumns, $linksUntil): array {
            $parts = Part::query()->where('project_id', $project->id)->orderBy('order')->get(['id', 'uuid', 'name', 'kind', 'order', 'current_take_id']);
            $takes = Take::query()->where('project_id', $project->id)->where('status', Take::READY)->orderBy('id')->get($takeColumns);
            $current = Take::query()->whereKey($parts->pluck('current_take_id')->filter()->all())->pluck('uuid', 'id');

            return [
                'id' => $project->uuid,
                'title' => $project->title,
                'bpm' => (float) $project->bpm,
                'key' => $project->music_key,
                'timeSignature' => $project->time_signature,
                'template' => $project->template_slug !== null ? ['id' => $project->template_slug, 'version' => $project->template_version] : null,
                'inBin' => $project->binned_at !== null,
                'parts' => $parts->map(static fn (Part $part): array => ['id' => $part->uuid, 'name' => $part->name, 'kind' => $part->kind, 'order' => $part->order, 'currentTakeId' => $current->get($part->current_take_id)])->all(),
                'takes' => $takes->map(static fn (Take $take): array => [
                    'id' => $take->uuid,
                    'part' => $parts->firstWhere('id', $take->part_id)?->name,
                    'sampleRate' => $take->sample_rate,
                    'frames' => $take->frames,
                    'recordedAt' => $take->created_at?->utc()->toIso8601ZuluString(),
                    'inBin' => $take->binned_at !== null,
                    'flac' => Storage::disk('private')->temporaryUrl((string) $take->file_key, $linksUntil),
                ])->values()->all(),
            ];
        });
        $this->addList($zip, 'projects.json', $projects);

        // Shared harmonies, with a link to each mix (as for the takes) and the reports they had.
        $harmonies = Harmony::query()->where('user_id', $user->id)->whereNull('deleted_at')->orderBy('id')->get()->map(static fn (Harmony $harmony): array => [
            'id' => $harmony->public_id,
            'title' => $harmony->title,
            'visibility' => $harmony->visibility,
            'page' => Hosts::public('h/'.$harmony->public_id),
            'sharedAt' => $harmony->created_at?->utc()->toIso8601ZuluString(),
            'signUps' => $harmony->sign_ups,
            'moderation' => $harmony->hidden_reason,
            'mix' => $harmony->audio_key !== null ? Storage::disk('private')->temporaryUrl($harmony->audio_key, $linksUntil) : null,
        ])->all();
        $zip->addFromString('shared-harmonies.json', (string) json_encode($harmonies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Their own templates, every version as made (so they can be imported again), and the
        // choir groups they're in.
        $templates = Template::query()->with(['versions', 'recordings'])->where('owner_id', $user->id)->whereNull('deleted_at')->orderBy('id')->get()->map(static fn (Template $template): array => [
            'id' => $template->slug,
            'visibility' => $template->visibility,
            'versions' => $template->versions->sortBy('version')->map(static fn (TemplateVersion $version): array => (array) $version->data)->values()->all(),
            'recordedGuides' => $template->recordings->where('status', TemplateRecording::READY)->map(static fn (TemplateRecording $recording): array => [
                'part' => $recording->part_key,
                'recordedAt' => $recording->created_at?->utc()->toIso8601ZuluString(),
                'aac' => Storage::disk('private')->temporaryUrl((string) $recording->file_key, $linksUntil),
            ])->values()->all(),
        ])->all();
        $zip->addFromString('my-templates.json', (string) json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        // What they sang on other people's projects is theirs too, and where they sing.
        $sung = Take::query()->select($takeColumns)->with(['project:id,title', 'part:id,name'])->where('recorded_by', $user->id)->where('user_id', '!=', $user->id)->where('status', Take::READY)->orderBy('id')->get()->map(static fn (Take $take): array => [
            'project' => $take->project->title,
            'part' => $take->part?->name,
            'recordedAt' => $take->created_at?->utc()->toIso8601ZuluString(),
            'flac' => Storage::disk('private')->temporaryUrl((string) $take->file_key, $linksUntil),
        ])->all();
        $zip->addFromString('sung-on-shared-projects.json', (string) json_encode([
            'projects' => ProjectMember::query()->with('project')->where('user_id', $user->id)->get()->map(static fn (ProjectMember $member): array => ['project' => $member->project->title, 'joinedAt' => $member->created_at?->utc()->toIso8601ZuluString()])->all(),
            'takes' => $sung,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $groups = GroupMember::query()->with('group')->where('user_id', $user->id)->get()->map(static fn (GroupMember $member): array => ['group' => $member->group->name, 'role' => $member->role, 'joinedAt' => $member->created_at?->utc()->toIso8601ZuluString()])->all();
        $zip->addFromString('choir-groups.json', (string) json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Coaching: what they bought (and paid, and were refunded), the reviews they were given, and
        // as a coach, what they sold and how they were rated.
        $engagements = Engagement::query()->with(['coach', 'reviews'])->where('singer_id', $user->id)->orderBy('id')->get()->map(static fn (Engagement $e): array => [
            'kind' => $e->kind,
            'coach' => $e->coach->name ?? __('coaching.deleted_account'),
            'status' => $e->status,
            'priceCents' => $e->price_cents,
            'currency' => $e->currency,
            'refundedCents' => $e->refunded_cents,
            'createdAt' => $e->created_at?->utc()->toIso8601ZuluString(),
            'reviews' => $e->reviews->map(static fn (ReviewItem $r): array => ['question' => $r->question, 'review' => $r->delivered, 'deliveredAt' => $r->delivered_at?->utc()->toIso8601ZuluString()])->values()->all(),
        ])->all();
        $coached = CoachRating::query()->where('coach_id', $user->id)->orderBy('id')->get()->map(static fn (CoachRating $r): array => ['stars' => $r->stars, 'text' => $r->text, 'reply' => $r->reply, 'at' => $r->created_at?->utc()->toIso8601ZuluString()])->all();
        $sold = Engagement::query()->where('coach_id', $user->id)->orderBy('id')->get()->map(static fn (Engagement $e): array => ['kind' => $e->kind, 'status' => $e->status, 'priceCents' => $e->price_cents, 'currency' => $e->currency, 'createdAt' => $e->created_at?->utc()->toIso8601ZuluString()])->all();
        $messages = CoachMessage::query()->where('sender_id', $user->id)->orderBy('id')->get()->map(static fn (CoachMessage $m): array => ['text' => $m->body, 'sentAt' => $m->created_at?->utc()->toIso8601ZuluString()])->all();
        $zip->addFromString('coaching.json', (string) json_encode(['bought' => $engagements, 'sold' => $sold, 'ratingsReceived' => $coached, 'messagesSent' => $messages], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // A beta singer's own records, payments included: their data, in their own export (the
        // right of access), never shown in the app (Hillel's rule; docs/decisions.md).
        $singer = BetaSinger::query()->with(['assignments.template', 'assignments.submissions' => fn ($query) => $query->select(['id', 'beta_assignment_id', 'status', 'payable_cents', 'paid_at', 'singer_note', 'device', 'mic', 'created_at'])])->where('user_id', $user->id)->first();

        if ($singer !== null) {
            $zip->addFromString('beta-singer.json', (string) json_encode([
                'agreement' => ['version' => $singer->consent_version, 'acceptedAt' => $singer->consented_at?->utc()->toIso8601ZuluString()],
                'credit' => $singer->credit_mode?->value,
                'hourlyRateCents' => $singer->hourly_rate_cents,
                'currency' => $singer->currency,
                'parts' => $singer->assignments->map(static fn (BetaAssignment $a): array => [
                    'song' => $a->template?->title,
                    'part' => $a->part_key,
                    'courseClip' => $a->clip?->slot,
                    'minutes' => $a->minutes,
                    'takes' => $a->submissions->map(static fn (BetaSubmission $s): array => [
                        'sentAt' => $s->created_at?->utc()->toIso8601ZuluString(),
                        'status' => $s->status->value,
                        'payableCents' => $s->payable_cents,
                        'paidAt' => $s->paid_at?->utc()->toIso8601ZuluString(),
                        'yourNote' => $s->singer_note,
                        'device' => $s->device,
                        'mic' => $s->mic,
                    ])->values()->all(),
                ])->values()->all(),
                'feedback' => DB::table('beta_feedback')->where('beta_singer_id', $singer->id)->orderBy('id')->get(['template_slug', 'part_key', 'beat', 'text', 'created_at'])->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        foreach ($this->records($user, $linksUntil) as $name => $query) {
            $this->addList($zip, 'records/'.$name.'.json', $query);
        }

        $learning = UserLearning::query()->where('user_id', $user->id)->first();
        $zip->addFromString('course-progress.json', (string) json_encode((object) ($learning->progress ?? []), JSON_PRETTY_PRINT));

        // Kept for 30 days after "Start over", for its undo: it's theirs too.
        if ($learning?->reset_snapshot !== null) {
            $zip->addFromString('course-progress-before-reset.json', (string) json_encode((object) $learning->reset_snapshot, JSON_PRETTY_PRINT));
        }

        if ($user->avatar_key !== null && Storage::disk('media')->exists($user->avatar_key)) {
            $zip->addFromString('avatar.jpg', (string) Storage::disk('media')->get($user->avatar_key));
        }

        $zip->close();

        foreach ($this->lists as $list) {
            @unlink($list);
        }

        $key = 'exports/'.Str::lower(Str::random(32)).'.zip';
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Couldn't open the export at {$path}.");
        }

        Storage::disk('private')->writeStream($key, $stream);
        is_resource($stream) && fclose($stream);
        @unlink($path);

        $expiresAt = now()->addDays(DataExport::DAYS);
        $export->forceFill(['status' => 'ready', 'file_key' => $key, 'ready_at' => now(), 'expires_at' => $expiresAt])->save();

        if ($user->email !== null) {
            Mail::to($user->email)->send(new ExportReady(url('/account/export'), $expiresAt->copy()->settings(['locale' => $user->locale])->isoFormat('LL')));
        }
    }

    /**
     * Temporary files holding the lists, read when the ZIP closes.
     *
     * @var list<string>
     */
    private array $lists = [];

    /**
     * A JSON list written to a temporary file one row at a time, added to the ZIP from there.
     *
     * @param  iterable<mixed>  $rows
     */
    private function addList(ZipArchive $zip, string $name, iterable $rows): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'list');
        $file = fopen($path, 'wb');

        if ($file === false) {
            throw new RuntimeException("Couldn't write {$path}.");
        }

        fwrite($file, '[');
        $first = true;

        foreach ($rows as $row) {
            fwrite($file, ($first ? "\n" : ",\n").json_encode(self::decoded($row), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $first = false;
        }

        fwrite($file, "\n]\n");
        fclose($file);
        $this->lists[] = $path;
        $zip->addFile($path, $name);
    }

    /**
     * A row with its JSON columns as JSON, not as strings of it.
     */
    private static function decoded(mixed $row): mixed
    {
        if (! $row instanceof stdClass) {
            return $row;
        }

        foreach (get_object_vars($row) as $column => $value) {
            if (is_string($value) && ($value[0] ?? '') !== '' && in_array($value[0], ['{', '['], true) && json_validate($value)) {
                $row->{$column} = json_decode($value);
            }
        }

        return $row;
    }

    /**
     * Everything else we hold about them, a list per kind, never another person's id, a secret
     * or a file's key (a recording is a link instead).
     *
     * @return array<string, iterable<mixed>>
     */
    private function records(User $user, Carbon $linksUntil): array
    {
        $id = $user->id;
        $engagements = static fn (string $as) => DB::table('engagements')->where($as, $id)->select('id');
        $lazy = static fn (Builder $query): iterable => $query->lazy(500);

        return [
            'pitch-history' => $lazy(DB::table('scores')->where('user_id', $id)->orderBy('id')->select(['take_uuid', 'template_slug', 'template_version', 'part_key', 'lesson', 'recorded_at', 'summary'])),
            'support-messages' => $lazy(DB::table('support_messages')->where(fn ($query) => $query->where('user_id', $id)->when($user->email !== null, fn ($q) => $q->orWhere('email', $user->email)))->orderBy('id')->select(['id', 'email', 'category', 'body', 'context', 'status', 'answered_at', 'closed_at', 'created_at'])),
            'support-replies' => $lazy(DB::table('support_replies')->whereIn('support_message_id', DB::table('support_messages')->where('user_id', $id)->when($user->email !== null, fn ($q) => $q->orWhere('email', $user->email))->select('id'))->orderBy('id')->select(['support_message_id', 'body', 'created_at'])),
            'notices-and-appeals' => $lazy(DB::table('notices')->where('user_id', $id)->orderBy('id')->select(['kind', 'rule', 'note', 'automatic', 'appeal_text', 'appealed_at', 'decision', 'decision_note', 'decided_at', 'created_at'])),
            'reports-you-sent' => $lazy(DB::table('reports')->where('reporter_id', $id)->orderBy('id')->select(['reportable_type', 'reason', 'note', 'status', 'resolved_at', 'created_at'])),
            'coaching-problems-you-reported' => $lazy(DB::table('engagement_reports')->where('user_id', $id)->orderBy('id')->select(['text', 'resolved_at', 'created_at'])),
            'coach-ratings-you-gave' => $lazy(DB::table('coach_ratings')->where('singer_id', $id)->orderBy('id')->select(['stars', 'text', 'reply', 'replied_at', 'removed_at', 'created_at'])),
            'lessons' => $lazy(DB::table('lesson_bookings')->where(fn ($query) => $query->whereIn('engagement_id', $engagements('singer_id'))->orWhereIn('engagement_id', $engagements('coach_id')))->orderBy('id')->select(['status', 'starts_at', 'ends_at', 'timezone', 'guest_timezone', 'notes', 'refund_until', 'cancelled_at', 'completed_at', 'created_at'])),
            'exercise-packs-you-bought' => $lazy(DB::table('pack_purchases')->join('exercise_packs', 'exercise_packs.id', '=', 'pack_purchases.pack_id')->where('pack_purchases.user_id', $id)->orderBy('pack_purchases.id')->select(['exercise_packs.title', 'pack_purchases.created_at'])),
            'teacher-links' => $lazy(DB::table('teacher_links')->where(fn ($query) => $query->where('singer_id', $id)->orWhere('teacher_id', $id))->orderBy('id')->select(['name', 'status', 'created_at'])),
            'coach-application' => $lazy(DB::table('coach_applications')->where('user_id', $id)->orderBy('id')->select(['answers', 'sample_review', 'agreement_version', 'status', 'decided_at', 'note', 'created_at'])),
            'coach-profile' => $lazy(DB::table('coach_profiles')->where('user_id', $id)->orderBy('id')->select(['status', 'bio', 'voice_types', 'styles', 'languages', 'timezone', 'cancellation_hours', 'stripe_account_id', 'charges_enabled', 'payouts_enabled', 'rating_average', 'rating_count', 'approved_at', 'created_at'])),
            'coach-offerings' => $lazy(DB::table('coach_offerings')->where('coach_id', $id)->orderBy('id')->select(['kind', 'title', 'description', 'price_cents', 'currency', 'turnaround_days', 'max_seconds', 'reviews_per_month', 'duration_minutes', 'archived_at', 'created_at'])),
            'exercise-packs-you-made' => $lazy(DB::table('exercise_packs')->where('coach_id', $id)->orderBy('id')->select(['title', 'description', 'price_cents', 'currency', 'exercises', 'status', 'note', 'reviewed_at', 'created_at'])),
            'reviews-you-gave' => $lazy(DB::table('review_items')->whereIn('engagement_id', $engagements('coach_id'))->orderBy('id')->select(['question', 'delivered', 'delivered_at', 'follow_up_question', 'follow_up_answer', 'created_at'])),
            'review-recordings-you-made' => DB::table('review_media')->where('user_id', $id)->where('status', 'ready')->orderBy('id')->select(['kind', 'sample_rate', 'frames', 'file_key', 'created_at'])->lazy(500)->map(static fn (stdClass $media): array => [
                'kind' => $media->kind,
                'sampleRate' => $media->sample_rate,
                'frames' => $media->frames,
                'recordedAt' => $media->created_at,
                'file' => $media->file_key !== null ? Storage::disk('private')->temporaryUrl((string) $media->file_key, $linksUntil) : null,
            ]),
            'plus-subscriptions' => $lazy(DB::table('subscriptions')->where('user_id', $id)->orderBy('id')->select(['type', 'stripe_status', 'trial_ends_at', 'ends_at', 'created_at'])),
            'plus-checkouts' => $lazy(DB::table('checkout_sessions')->where('user_id', $id)->orderBy('id')->select(['moment', 'interval', 'currency', 'trial', 'completed_at', 'created_at'])),
            'songs-unlocked' => $lazy(DB::table('template_unlocks')->join('templates', 'templates.id', '=', 'template_unlocks.template_id')->where('template_unlocks.user_id', $id)->orderBy('template_unlocks.id')->select(['templates.slug', 'template_unlocks.created_at'])),
            'payment-disputes' => $lazy(DB::table('billing_disputes')->where('user_id', $id)->orderBy('id')->select(['amount_cents', 'currency', 'reason', 'status', 'created_at'])),
            'reminder-devices' => $lazy(DB::table('push_devices')->where('user_id', $id)->orderBy('id')->select(['last_sent_at', 'created_at'])),
            'devices-signed-in' => $lazy(DB::table('user_devices')->where('user_id', $id)->orderBy('id')->select(['last_seen_at', 'created_at'])),
            'passkeys' => $lazy(DB::table('passkeys')->where('user_id', $id)->orderBy('id')->select(['name', 'last_used_at', 'created_at'])),
            'event-schedule' => $lazy(DB::table('es_connections')->where('user_id', $id)->orderBy('id')->select(['mode', 'base_url', 'es_user_name', 'schedule_name', 'schedule_timezone', 'status', 'disconnected_at', 'created_at'])),
            'choir-parts' => $lazy(DB::table('group_assignments')->join('group_songs', 'group_songs.id', '=', 'group_assignments.group_song_id')->join('templates', 'templates.id', '=', 'group_songs.template_id')->where('group_assignments.user_id', $id)->orderBy('group_assignments.id')->select(['templates.slug', 'group_assignments.part_key', 'group_assignments.created_at'])),
            'share-videos' => $lazy(DB::table('share_videos')->where('user_id', $id)->orderBy('id')->select(['start_ms', 'end_ms', 'look', 'words', 'status', 'expires_at', 'created_at'])),
            'project-invites-you-made' => $lazy(DB::table('project_invites')->where('created_by', $id)->orderBy('id')->select(['part_name', 'expires_at', 'used_at', 'revoked_at', 'created_at'])),
            // The gallery (N1).
            'gallery-likes' => $lazy(DB::table('harmony_likes')->join('harmonies', 'harmonies.id', '=', 'harmony_likes.harmony_id')->where('harmony_likes.user_id', $id)->orderBy('harmony_likes.id')->select(['harmonies.public_id', 'harmony_likes.created_at'])),
            'gallery-comments' => $lazy(DB::table('comments')->join('harmonies', 'harmonies.id', '=', 'comments.harmony_id')->where('comments.user_id', $id)->orderBy('comments.id')->select(['harmonies.public_id', 'comments.body', 'comments.held_at', 'comments.hidden_at', 'comments.created_at'])),
            'gallery-following' => $lazy(DB::table('follows')->join('users', 'users.id', '=', 'follows.followee_id')->where('follows.follower_id', $id)->orderBy('follows.id')->select(['users.handle', 'follows.created_at'])),
            'gallery-blocked' => $lazy(DB::table('user_blocks')->join('users', 'users.id', '=', 'user_blocks.blocked_id')->where('user_blocks.blocker_id', $id)->orderBy('user_blocks.id')->select(['users.handle', 'user_blocks.created_at'])),
            'leagues' => $lazy(DB::table('league_members')->join('leagues', 'leagues.id', '=', 'league_members.league_id')->where('league_members.user_id', $id)->orderBy('league_members.id')->select(['leagues.week', 'leagues.tier', 'league_members.xp', 'league_members.promoted', 'league_members.created_at'])),
            // Plus bought in an app (never its token), the app devices signed in, and their pushes' settings
            // are in account.json.
            'store-purchases' => $lazy(DB::table('store_subscriptions')->where('user_id', $id)->orderBy('id')->select(['store', 'product_id', 'interval', 'status', 'expires_at', 'grace_until', 'will_renew', 'environment', 'refunded_at', 'created_at'])),
            'app-devices' => $lazy(DB::table('app_devices')->where('user_id', $id)->orderBy('id')->select(['name', 'platform', 'model', 'app_version', 'method', 'signed_in_at', 'last_used_at', 'revoked_at', DB::raw('push_service is not null as push'), 'created_at'])),
            // The bell: what happened, never who (the people are named in the app).
            'bell' => $lazy(DB::table('bell_items')->where('user_id', $id)->orderBy('id')->select(['kind', 'others', 'read_at', 'created_at'])),
            // Ideas: their own tunes shared, and the voices they sang on anyone's (each a link).
            'ideas' => $lazy(DB::table('ideas')->join('templates', 'templates.id', '=', 'ideas.template_id')->where('ideas.user_id', $id)->orderBy('ideas.id')->select(['ideas.public_id', 'templates.title', 'ideas.note', 'ideas.voicing', 'ideas.part_keys', 'ideas.held_at', 'ideas.hidden_at', 'ideas.completed_at', 'ideas.created_at'])),
            'idea-voices' => DB::table('idea_voices')->join('ideas', 'ideas.id', '=', 'idea_voices.idea_id')->where('idea_voices.user_id', $id)->orderBy('idea_voices.id')->select(['ideas.public_id', 'idea_voices.part_key', 'idea_voices.status', 'idea_voices.file_key', 'idea_voices.hidden_at', 'idea_voices.ready_at', 'idea_voices.created_at'])->lazy(500)->map(static fn (stdClass $voice): array => [
                'idea' => $voice->public_id,
                'part' => $voice->part_key,
                'status' => $voice->status,
                'hiddenAt' => $voice->hidden_at,
                'readyAt' => $voice->ready_at,
                'createdAt' => $voice->created_at,
                'file' => $voice->file_key !== null ? Storage::disk('private')->temporaryUrl((string) $voice->file_key, $linksUntil) : null,
            ]),
            // Their part leaderboard scores, the people connected to them for friends' boards, and
            // the challenge links they made (each token kept only as a hash, so not here).
            'part-scores' => DB::table('part_scores')->join('templates', 'templates.id', '=', 'part_scores.template_id')->join('template_versions', 'template_versions.id', '=', 'part_scores.template_version_id')->where('part_scores.user_id', $id)->orderBy('part_scores.id')->select(['templates.slug', 'template_versions.version', 'part_scores.part_key', 'part_scores.score', 'part_scores.scoring_version', 'part_scores.mode', 'part_scores.output', 'part_scores.hidden_at', 'part_scores.recorded_at'])->lazy(500)->map(static fn (stdClass $score): array => [
                'song' => $score->slug,
                'version' => (int) $score->version,
                'part' => $score->part_key,
                'score' => (int) $score->score,
                'scoringVersion' => (int) $score->scoring_version,
                'mode' => $score->mode,
                'output' => $score->output,
                'hiddenAt' => $score->hidden_at,
                'recordedAt' => $score->recorded_at,
            ]),
            'board-friends' => DB::table('board_connections')->join('users', 'users.id', '=', 'board_connections.friend_id')->where('board_connections.user_id', $id)->orderBy('board_connections.id')->select(['users.name', 'board_connections.source', 'board_connections.created_at'])->lazy(500)->map(static fn (stdClass $c): array => ['name' => $c->name, 'source' => $c->source, 'since' => $c->created_at]),
            'board-challenges' => DB::table('board_challenges')->join('templates', 'templates.id', '=', 'board_challenges.template_id')->where('board_challenges.user_id', $id)->orderBy('board_challenges.id')->select(['templates.slug', 'board_challenges.part_key', 'board_challenges.created_at'])->lazy(500)->map(static fn (stdClass $c): array => ['song' => $c->slug, 'part' => $c->part_key, 'createdAt' => $c->created_at]),
            // Their voices in the virtual choirs (each a link).
            'choir-voices' => DB::table('choir_voices')->join('choirs', 'choirs.id', '=', 'choir_voices.choir_id')->join('templates', 'templates.id', '=', 'choirs.template_id')->where('choir_voices.user_id', $id)->orderBy('choir_voices.id')->select(['templates.slug', 'choir_voices.part_key', 'choir_voices.status', 'choir_voices.credit', 'choir_voices.score', 'choir_voices.file_key', 'choir_voices.in_mix_at', 'choir_voices.hidden_at', 'choir_voices.created_at'])->lazy(500)->map(static fn (stdClass $voice): array => [
                'song' => $voice->slug,
                'part' => $voice->part_key,
                'status' => $voice->status,
                'credit' => $voice->credit,
                'score' => $voice->score,
                'inMixAt' => $voice->in_mix_at,
                'hiddenAt' => $voice->hidden_at,
                'createdAt' => $voice->created_at,
                'file' => $voice->file_key !== null ? Storage::disk('private')->temporaryUrl((string) $voice->file_key, $linksUntil) : null,
            ]),
            'song-ratings' => $lazy(DB::table('template_ratings')->join('templates', 'templates.id', '=', 'template_ratings.template_id')->where('template_ratings.user_id', $id)->orderBy('template_ratings.id')->select(['templates.slug', 'template_ratings.stars', 'template_ratings.created_at'])),
            'song-requests' => $lazy(DB::table('song_requests')->where('user_id', $id)->orderBy('id')->select(['title', 'writer', 'why', 'status', 'votes_count', 'released_at', 'created_at'])),
            'song-request-votes' => $lazy(DB::table('song_request_votes')->join('song_requests', 'song_requests.id', '=', 'song_request_votes.song_request_id')->where('song_request_votes.user_id', $id)->orderBy('song_request_votes.id')->select(['song_requests.title', 'song_request_votes.created_at'])),
            'gallery-consents' => $lazy(DB::table('harmony_consents')->join('harmonies', 'harmonies.id', '=', 'harmony_consents.harmony_id')->where('harmony_consents.user_id', $id)->orderBy('harmony_consents.id')->select(['harmonies.public_id', 'harmony_consents.status', 'harmony_consents.decided_at', 'harmony_consents.created_at'])),
        ];
    }
}
