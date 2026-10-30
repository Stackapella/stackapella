<?php

declare(strict_types=1);

namespace App\Support\Accounts;

/**
 * Every column that points at a person (docs/decisions.md, "the export and deletion audit"), and
 * where their export (BuildExport) has it, or why it doesn't (staff's own actions, secrets, other
 * people's data, bookkeeping). Deleting the account removes or anonymises each by its foreign key
 * (CASCADE or SET NULL), or DeleteAccount does (the two without one). The test
 * PersonalDataTest fails when a new column pointing at a person isn't listed here.
 */
final class PersonalData
{
    /**
     * The account's own columns (users): the key account.json has it under, or why it isn't there.
     * PersonalDataTest fails when a new users column isn't listed here.
     *
     * @var array<string, string|array{0: null, 1: string}>
     */
    public const ACCOUNT = [
        'id' => [null, 'Our own key: the export names them by their account instead.'],
        'name' => 'name', 'handle' => 'handle', 'email' => 'email', 'email_verified_at' => 'emailVerifiedAt',
        'password' => [null, 'A secret: only its hash is kept.'],
        'avatar_key' => [null, 'Their picture is avatar.jpg in the export, not its storage key.'],
        'bio' => 'bio', 'public_profile_at' => 'publicProfileSince', 'hide_profile' => 'hideProfile', 'initials' => 'initials', 'boards_name' => 'boardsName', 'league_joined_at' => 'league', 'league_tier' => 'league',
        'x_handle' => 'xHandle', 'bluesky_handle' => 'blueskyHandle',
        'google_id' => 'signInMethods', 'apple_id' => 'signInMethods', 'event_schedule_id' => 'signInMethods',
        'is_admin' => [null, "Staff's own role."], 'is_editor' => [null, "Staff's own role."],
        'locale' => 'locale', 'appearance' => 'appearance', 'plan' => 'plan',
        'sync_seq' => [null, 'Bookkeeping: where their devices are in the sync.'], 'sync_pruned_seq' => [null, 'Bookkeeping: where their devices are in the sync.'],
        'timezone' => 'timezone', 'age_confirmed_at' => 'ageConfirmedAt', 'adult_confirmed_at' => 'adultConfirmedAt', 'birth_month' => 'birthMonth', 'birth_year' => 'birthYear',
        'terms_version' => 'termsAccepted', 'terms_accepted_at' => 'termsAccepted',
        'ref' => 'ref', 'early_access_link_id' => 'joinedThroughEarlyAccess',
        'pending_invite_hash' => [null, 'A secret: the hash of an invite they were opening.'],
        'heard_from' => 'heardFrom', 'country' => 'country', 'board_global_at' => 'onGlobalBoard',
        'trust_level_override' => [null, "Staff's own decision; any limit it sets reaches them as a notice (notices.json)."],
        'trust_note' => [null, "Staff's own note; a limit's reason reaches them as a notice."],
        'trust_lowered_until' => [null, 'Bookkeeping for a limit they were told of by a notice.'],
        'suspended_at' => [null, 'A suspension reaches them as a notice, emailed.'],
        'remember_token' => [null, 'A secret: the sign-in cookie.'],
        'created_at' => 'createdAt', 'updated_at' => [null, 'Bookkeeping.'],
        'stripe_id' => [null, "Stripe's own reference; their payments are in records/plus-checkouts.json and Stripe's receipts."],
        'store_account_token' => [null, 'A secret the stores echo back to match a purchase.'],
        'store_plus_until' => 'storePlusUntil', 'store_tester_at' => [null, "Staff's own decision."],
        'pm_type' => 'plus', 'pm_last_four' => 'plus',
        'trial_ends_at' => [null, "Stripe's copy; the subscription is in the plan and Stripe's receipts."], 'trial_used_at' => 'trialUsedAt',
        'trial_subscription_id' => [null, "Stripe's own reference."],
        'plus_renews_at' => 'plus', 'past_due_until' => 'plus', 'plus_paid_at' => 'plus', 'plus_ended_at' => 'plus', 'plus_ended_reason' => 'plus', 'plus_paid_until' => 'plusPaidUntil',
        'billing_notice' => [null, 'A notice the app shows once; nothing of theirs.'], 'billing_notice_at' => [null, 'A notice the app shows once; nothing of theirs.'],
        'editable_project_ids' => [null, 'Bookkeeping: which projects stay editable on the free plan.'],
        'reminder_enabled' => 'practiceReminders', 'reminder_days' => 'practiceReminders', 'reminder_time' => 'practiceReminders', 'reminder_timezone' => 'practiceReminders',
        'reminder_sent_on' => [null, 'Bookkeeping: the last day a reminder went.'],
        'signup_harmony_id' => [null, 'Bookkeeping: the shared song they signed up from (a count, never shown).'],
        'coach_active_at' => 'coachSince', 'idea_emails' => 'ideaEmails', 'push_settings' => 'appPushes',
        'apple_refresh_token' => [null, 'A secret, revoked with Apple when they leave.'], 'apple_web_refresh_token' => [null, 'A secret, revoked with Apple when they leave.'],
    ];

    /**
     * `table.column` => [the export's file, or null; why not, or null].
     *
     * @var array<string, array{0: string|null, 1: string|null}>
     */
    public const COLUMNS = [
        'admin_second_factors.user_id' => [null, "Staff only: a second factor's secret."],
        'app_devices.user_id' => ['records/app-devices.json', null],
        'app_pushes.user_id' => [null, 'A push waiting to go, kept a week; its words are the bell'],
        'audit_logs.actor_id' => [null, "Staff's own actions."],
        'bell_items.actor_id' => [null, "Names them in another person's bell."],
        'bell_items.user_id' => ['records/bell.json', null],
        'beta_singers.created_by' => [null, "Staff's own actions."],
        'beta_singers.user_id' => ['beta-singer.json', null],
        'beta_submissions.reviewed_by' => [null, "Staff's own actions."],
        'billing_disputes.user_id' => ['records/payment-disputes.json', null],
        'blocks.created_by' => [null, "Staff's own actions."],
        'board_challenges.user_id' => ['records/board-challenges.json', null],
        'board_connections.friend_id' => [null, "Names them in another person's friends' boards."],
        'board_connections.user_id' => ['records/board-friends.json', null],
        'checkout_sessions.user_id' => ['records/plus-checkouts.json', null],
        'choir_voices.user_id' => ['records/choir-voices.json', null],
        'coach_applications.decided_by' => [null, "Staff's own actions."],
        'coach_applications.user_id' => ['records/coach-application.json', null],
        'coach_messages.sender_id' => ['coaching.json', null],
        'coach_offerings.coach_id' => ['records/coach-offerings.json', null],
        'coach_profiles.user_id' => ['records/coach-profile.json', null],
        'coach_ratings.coach_id' => ['coaching.json', null],
        'coach_ratings.singer_id' => ['records/coach-ratings-you-gave.json', null],
        'coaching_payments.coach_id' => ['coaching.json', null],
        'comments.user_id' => ['records/gallery-comments.json', null],
        'daily_scores.user_id' => ['records/daily-scores.json', null],
        'data_exports.user_id' => [null, 'The export itself.'],
        'email_tokens.user_id' => [null, 'A secret, for one use within a day (a week to undo an email change).'],
        'engagement_reports.user_id' => ['records/coaching-problems-you-reported.json', null],
        'engagements.coach_id' => ['coaching.json', null],
        'engagements.singer_id' => ['coaching.json', null],
        'es_connections.user_id' => ['records/event-schedule.json', null],
        'exercise_packs.coach_id' => ['records/exercise-packs-you-made.json', null],
        'exercise_packs.reviewed_by' => [null, "Staff's own actions."],
        'flag_users.user_id' => [null, 'A feature switched on for them by staff, for a test.'],
        'flags.updated_by' => [null, "Staff's own actions."],
        'follows.followee_id' => [null, "Who follows them is the followers' own data."],
        'follows.follower_id' => ['records/gallery-following.json', null],
        'ghosts.user_id' => ['records/ghosts.json', null],
        'group_assignments.user_id' => ['records/choir-parts.json', null],
        'group_members.user_id' => ['choir-groups.json', null],
        'groups.director_id' => ['choir-groups.json', null],
        'handle_holds.user_id' => [null, 'A handle they gave up, held from reuse for a while.'],
        'harmonies.user_id' => ['shared-harmonies.json', null],
        'harmony_consents.user_id' => ['records/gallery-consents.json', null],
        'harmony_likes.user_id' => ['records/gallery-likes.json', null],
        'harmony_stems.user_id' => [null, "Their voice inside another person's harmony; their own takes are in projects.json."],
        'idea_voices.user_id' => ['records/idea-voices.json', null],
        'ideas.user_id' => ['records/ideas.json', null],
        'invoice_ninja_connections.user_id' => [null, "Staff's books connection."],
        'invoice_ninja_records.user_id' => [null, "The books' copy of a payment, which is in coaching.json or the Plus records."],
        'league_members.user_id' => ['records/leagues.json', null],
        'mudeo_batches.created_by' => [null, "Staff's own actions."],
        'notices.decided_by' => [null, "Staff's own actions."],
        'notices.user_id' => ['records/notices-and-appeals.json', null],
        'pack_purchases.user_id' => ['records/exercise-packs-you-bought.json', null],
        'part_scores.user_id' => ['records/part-scores.json', null],
        'parts.created_by' => ['projects.json', null],
        'passkeys.user_id' => ['records/passkeys.json', null],
        'project_exits.user_id' => [null, 'Sync bookkeeping: that they left a shared project.'],
        'project_invites.created_by' => ['records/project-invites-you-made.json', null],
        'project_invites.used_by' => [null, "The invite is its maker's record; the projects they joined are in sung-on-shared-projects.json."],
        'project_members.user_id' => ['sung-on-shared-projects.json', null],
        'projects.user_id' => ['projects.json', null],
        'push_devices.user_id' => ['records/reminder-devices.json', null],
        'reports.reporter_id' => ['records/reports-you-sent.json', null],
        'reports.resolved_by' => [null, "Staff's own actions."],
        'review_media.user_id' => ['records/review-recordings-you-made.json', null],
        'scores.user_id' => ['records/pitch-history.json', null],
        'security_events.user_id' => ['sign-ins.json', null],
        'sessions.user_id' => [null, "The browser's own session."],
        'settings.updated_by' => [null, "Staff's own actions."],
        'share_videos.user_id' => ['records/share-videos.json', null],
        'social_posts.approved_by' => [null, "Staff's own actions."],
        'song_request_votes.user_id' => ['records/song-request-votes.json', null],
        'song_requests.user_id' => ['records/song-requests.json', null],
        'store_subscriptions.user_id' => ['records/store-purchases.json', null],
        'subscriptions.user_id' => ['records/plus-subscriptions.json', null],
        'support_messages.user_id' => ['records/support-messages.json', null],
        'support_replies.admin_id' => [null, "Staff's own actions."],
        'sync_tombstones.user_id' => [null, 'Sync bookkeeping: what was deleted, so other devices delete it too.'],
        'takes.recorded_by' => ['sung-on-shared-projects.json', null],
        'takes.user_id' => ['projects.json', null],
        'teacher_links.singer_id' => ['records/teacher-links.json', null],
        'teacher_links.teacher_id' => ['records/teacher-links.json', null],
        'template_guides.credit_user_id' => [null, 'The credit on a published guide; their recordings are in beta-singer.json and my-templates.json.'],
        'template_ratings.user_id' => ['records/song-ratings.json', null],
        'template_recordings.user_id' => ['my-templates.json', null],
        'template_unlocks.user_id' => ['records/songs-unlocked.json', null],
        'templates.owner_id' => ['my-templates.json', null],
        'trial_fingerprints.user_id' => [null, "A card's hash, so a card has one trial."],
        'user_blocks.blocked_id' => [null, "Who blocked them is the blockers' own data."],
        'user_blocks.blocker_id' => ['records/gallery-blocked.json', null],
        'user_devices.user_id' => ['records/devices-signed-in.json', null],
        'user_learning.user_id' => ['course-progress.json', null],
    ];

    /**
     * Links to a person that aren't a user id: a polymorphic pair (`*_type` and `*_id`, which can
     * name an account or what's theirs) and an email address column. Each says what happens to it
     * when the account goes. PersonalDataTest fails on a new one. Not covered by either list: ids
     * inside JSON columns (an audit entry's before and after, a bell item's data), which name
     * people by sqid at most.
     *
     * @var array<string, string>
     */
    public const INDIRECT = [
        'audit_logs.subject' => "Staff's record of what they acted on, an account among them: kept, as audit_logs.actor_id is.",
        'beta_singers.email' => 'In beta-singer.json; cleared when the account goes (ForgetSinger).',
        'email_suppressions.email' => 'Kept on purpose: an address we must never write to again (a bounce, a complaint or an unsubscribe).',
        'email_tokens.email' => 'Goes with the account (email_tokens.user_id cascades).',
        'mudeo_contacts.email' => "mudeo's old list, not an account: one invitation, and its own one-click unsubscribe.",
        'notices.subject' => 'What a notice is about, their own content; their notices go with the account (notices.user_id cascades).',
        'password_reset_tokens.email' => 'DeleteAccount deletes it; expired ones go daily (auth:clear-resets).',
        'reports.reportable' => 'Reports on their profile and their shared harmonies go with the account (DeleteAccount).',
        'sign_in_links.email' => "DeleteAccount deletes them; every link goes a day after it's made (app:accounts:prune).",
        'social_posts.postable' => 'Our own post about a shared harmony: withdrawn when the harmony goes (DeleteHarmony).',
        'support_messages.email' => 'DeleteAccount deletes what came from their address; the rest go after SupportMessage::KEEP_MONTHS.',
    ];

    /** Files the export writes only when there's something for them. */
    public const SOMETIMES = ['beta-singer.json', 'avatar.jpg', 'course-progress-before-reset.json'];
}
