<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Beta\ForgetSinger;
use App\Actions\Beta\ManageGuides;
use App\Actions\Books\ForgetBuyer;
use App\Actions\Choirs\Choirs;
use App\Actions\Coaching\Engagements;
use App\Actions\EventSchedule\Connections;
use App\Actions\Groups\ManageGroups;
use App\Actions\Ideas\Ideas;
use App\Actions\Ideas\IdeaVoices;
use App\Actions\Projects\Owned;
use App\Actions\Requests\SongRequests;
use App\Actions\Sharing\DeleteHarmony;
use App\Actions\Templates\Library;
use App\Enums\SecurityEventType;
use App\Jobs\DeletePrivateFiles;
use App\Models\AuditLog;
use App\Models\BetaSinger;
use App\Models\Comment;
use App\Models\DataExport;
use App\Models\Engagement;
use App\Models\ExercisePack;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\HandleHold;
use App\Models\Harmony;
use App\Models\HarmonyLike;
use App\Models\Part;
use App\Models\Report;
use App\Models\ReviewItem;
use App\Models\ReviewMedia;
use App\Models\SignInLink;
use App\Models\SongRequest;
use App\Models\SongRequestVote;
use App\Models\SupportMessage;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateRating;
use App\Models\TemplateRecording;
use App\Models\User;
use App\Services\Auth\ProviderTokens;
use App\Services\Billing\BillingGateway;
use App\Support\Accounts\Sessions;
use App\Support\Sync\SyncSeq;
use App\Support\Trust\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;

/**
 * Delete my account (iaw's DeleteAccount, Event Schedule's AccountDeletionService). Its handle is
 * held so nobody can take it over, its picture and exports go, every session ends, and the row
 * goes, with every recording (M4). Later milestones add theirs: the subscription (M5),
 * harmonies taken down (M6), a coach's payouts settled first (M8).
 */
final class DeleteAccount
{
    public function __construct(private readonly ChangeAvatar $avatar, private readonly Sessions $sessions, private readonly SecurityLog $securityLog, private readonly ForgetSinger $forget, private readonly BillingGateway $gateway, private readonly DeleteHarmony $deleteHarmony, private readonly SyncSeq $seq, private readonly ManageGroups $groups, private readonly Engagements $engagements, private readonly Connections $eventSchedule, private readonly ForgetBuyer $forgetBuyer) {}

    public function handle(User $user, ?string $ip): void
    {
        // A beta singer's guides go with the account (Hillel's decision; payments stay on record).
        $singer = BetaSinger::query()->where('user_id', $user->id)->first();

        if ($singer !== null && $singer->forgotten_at === null) {
            $this->forget->handle($singer, null, $ip);
        }

        // Coaching ends with the account (the engagements' money records stay, without who it was):
        // a coach's open reviews are refunded, a delivered one not yet released is paid to them (or
        // refunded if it can't be), and packages stop with the month refunded; a singer's
        // open reviews are refunded, a delivered one inside Unsend is paid to the coach, and their
        // packages stop now.
        foreach (Engagement::query()->where('coach_id', $user->id)->whereIn('status', [Engagement::REQUESTED, Engagement::ACCEPTED, Engagement::DELIVERED, Engagement::ACTIVE, Engagement::ENDING, Engagement::BOOKING, Engagement::BOOKED])->get() as $engagement) {
            $this->engagements->endForCoach($engagement);
        }
        foreach (Engagement::query()->where('singer_id', $user->id)->whereIn('status', [Engagement::REQUESTED, Engagement::ACCEPTED, Engagement::DELIVERED, Engagement::ACTIVE, Engagement::ENDING, Engagement::BOOKING, Engagement::BOOKED])->get() as $engagement) {
            $this->engagements->endForSinger($engagement);
        }
        // What they wrote to support goes too (and the answers): sent signed in, or from their address.
        SupportMessage::query()->where('user_id', $user->id)->when($user->email !== null, fn ($query) => $query->orWhere('email', $user->email))->delete();
        // Links waiting at their address (a password reset, a sign-in link): kept by email, not user.
        if ($user->email !== null) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            SignInLink::query()->where('email', $user->email)->delete();
        }
        // Their exercise packs go, except those someone bought: theirs to keep, without the coach.
        ExercisePack::query()->where('coach_id', $user->id)->whereDoesntHave('purchases')->delete();
        // Then the money's records lose who bought (amounts and dates stay).
        $this->forgetBuyer->handle($user);
        // Their Event Schedule connection: its webhook removed and its token revoked at ES (the
        // row goes with the account).
        $this->eventSchedule->forget($user);

        // Every file of the reviews on their engagements, both sides' (the take's copy, its pitch
        // track, the coach's voice notes and demos).
        $reviewIds = ReviewItem::query()->whereIn('engagement_id', Engagement::query()->where('singer_id', $user->id)->orWhere('coach_id', $user->id)->select('id'))->pluck('id');
        $coaching = ReviewItem::query()->whereKey($reviewIds)->get(['take_key', 'pitch_track_key'])
            ->flatMap(fn (ReviewItem $review): array => array_values(array_filter([$review->take_key, $review->pitch_track_key])))
            ->concat(ReviewMedia::query()->whereIn('review_item_id', $reviewIds)->orWhere('user_id', $user->id)->get(['file_key', 'upload_key'])->flatMap(fn (ReviewMedia $media): array => array_values(array_filter([$media->file_key, $media->upload_key]))))
            ->values()->all();
        ReviewMedia::query()->whereIn('review_item_id', $reviewIds)->delete();
        ReviewItem::query()->whereKey($reviewIds)->delete();

        // Plus stops billing with the account (backend plan D8): cancelled now, and Cashier's rows go.
        $subscription = $user->subscription('default');

        if ($subscription !== null && $subscription->valid()) {
            $this->gateway->cancelNow($subscription->stripe_id);
        }

        $this->avatar->remove($user);
        $this->sessions->endAll($user);

        // Sign in with Apple, in an app or on the web: Apple revokes what it gave us (App Store
        // Guideline 5.1.1(v)).
        app(ProviderTokens::class)->revokeAppleFor($user);

        // Shared pages go at once (off the CDN too), and the reports on them with them.
        // Locked and marked deleted first (DeleteHarmony), so one still processing never publishes.
        foreach (Harmony::query()->where('user_id', $user->id)->get() as $harmony) {
            $this->deleteHarmony->handle($harmony);
            Report::query()->whereMorphedTo('reportable', $harmony)->delete();
        }

        // And other people's shared harmonies with their voice in the mix.
        $this->deleteHarmony->withVoiceOf($user->id);
        // Reports on their profile are about nobody now.
        Report::query()->whereMorphedTo('reportable', $user)->delete();

        // Their ideas and every voice on them, and their voices on others' ideas, files too.
        app(Ideas::class)->removeForTemplates(array_values(array_map('intval', Template::query()->where('owner_id', $user->id)->pluck('id')->all())));
        app(IdeaVoices::class)->removeFor($user);
        app(Choirs::class)->removeFor($user);

        // Their votes on song requests go (the counts follow); a request they asked for stays for
        // the others who want it, without their reason.
        $voted = SongRequestVote::query()->where('user_id', $user->id)->pluck('song_request_id')->all();
        SongRequestVote::query()->where('user_id', $user->id)->delete();
        SongRequest::query()->where('user_id', $user->id)->update(['why' => null]);

        foreach (SongRequest::query()->whereKey($voted)->get() as $request) {
            SongRequests::recount($request);
        }

        // Guide voices credited to them, file and CDN copy too (the row's key alone would leave both).
        foreach (TemplateGuide::query()->where('credit_user_id', $user->id)->get() as $guide) {
            app(ManageGuides::class)->remove($guide, null, $ip);
        }

        // Their likes and comments go with the account (the keys cascade): the counts follow.
        foreach (HarmonyLike::query()->where('user_id', $user->id)->pluck('harmony_id') as $liked) {
            Harmony::query()->whereKey($liked)->where('likes_count', '>', 0)->decrement('likes_count');
        }
        foreach (Comment::query()->where('user_id', $user->id)->whereNull('held_at')->whereNull('hidden_at')->pluck('harmony_id') as $commented) {
            Harmony::query()->whereKey($commented)->where('comments_count', '>', 0)->decrement('comments_count');
        }

        // Their singing on other people's projects is theirs too: those takes go, and the parts
        // that played them go quiet, on the owners' devices as well (their sync).
        $sung = Take::query()->where('recorded_by', $user->id)->where('user_id', '!=', $user->id)->get();
        foreach ($sung as $take) {
            DB::transaction(function () use ($take): void {
                User::query()->whereKey($take->user_id)->lockForUpdate()->first(['id']);

                foreach (Part::query()->where('current_take_id', $take->id)->lockForUpdate()->get() as $part) {
                    $part->forceFill(['current_take_id' => null, 'version' => $part->version + 1, 'seq' => $this->seq->next($take->user_id)])->save();
                }

                $tombstone = new SyncTombstone(['kind' => 'take', 'uuid' => $take->uuid]);
                $tombstone->forceFill(['user_id' => $take->user_id, 'seq' => $this->seq->next($take->user_id)])->save();
                $take->delete();
            });
        }

        // The groups they direct go with them: their members' devices hear what that changes.
        $groupMembers = GroupMember::query()->whereIn('group_id', Group::query()->where('director_id', $user->id)->select('id'))->where('user_id', '!=', $user->id)->pluck('user_id')->all();

        // The parts they made on others' projects become the owners': the owners' devices hear it.
        foreach (Part::query()->with('project')->where('created_by', $user->id)->get() as $part) {
            DB::transaction(function () use ($part): void {
                Owned::lockAccount($part->project->user);
                Part::query()->whereKey($part->id)->update(['created_by' => null, 'version' => $part->version + 1, 'seq' => $this->seq->next($part->project->user_id), 'updated_at' => now()]);
            });
        }

        $exports = DataExport::query()->where('user_id', $user->id)->whereNotNull('file_key')->pluck('file_key')->all();
        // Every recording goes too (the rows with the account, the files in the background).
        // The creator's own recorded guides go with their templates (the rows by the account's cascade).
        $guides = TemplateRecording::query()->where('user_id', $user->id)->get(['file_key', 'upload_key']);
        $recordings = $guides->toBase()->concat($sung)->concat(Take::query()->where('user_id', $user->id)->get(['file_key', 'upload_key']))->flatMap(fn (Take|TemplateRecording $file): array => array_values(array_filter([$file->file_key, $file->upload_key])))->values()->all();

        // Their ratings of templates in the library go with the account (the key cascades): the
        // templates' counts follow.
        $rated = TemplateRating::query()->where('user_id', $user->id)->pluck('template_id')->all();
        TemplateRating::query()->where('user_id', $user->id)->delete();

        foreach (Template::query()->whereKey($rated)->get() as $template) {
            Library::recountRatings($template);
        }

        DB::transaction(function () use ($user, $ip): void {
            if ($user->handle !== null) {
                HandleHold::query()->firstOrCreate(['handle' => $user->handle]);
            }

            // Kept without who it was: the events stay for abuse, the audit log is append-only.
            $this->securityLog->record(SecurityEventType::AccountDeleted, null, $ip, ['user' => $user->sqid()]);
            AuditLog::record('account.deleted', null, null, after: ['user' => $user->sqid()], ip: $ip);

            $user->passkeys()->delete();
            $subscriptionIds = Subscription::query()->where('user_id', $user->id)->pluck('id');
            SubscriptionItem::query()->whereIn('subscription_id', $subscriptionIds)->delete();
            Subscription::query()->whereKey($subscriptionIds)->delete();
            $user->delete();
        });

        Storage::disk('private')->delete($exports);

        $this->groups->refresh($groupMembers);

        // 100 a job: the bucket deletes one key per request, and a job has 60 seconds.
        foreach (array_chunk([...$recordings, ...$coaching], 100) as $keys) {
            DeletePrivateFiles::dispatch($keys);
        }
    }
}
