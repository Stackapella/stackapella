<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Books\BookConnections;
use App\Actions\Ideas\Ideas;
use App\Actions\Leagues\Leagues;
use App\Actions\Templates\Library;
use App\Actions\Templates\SyncOfficialTemplates;
use App\Enums\CreditMode;
use App\Enums\SubmissionStatus;
use App\Enums\TemplateAccess;
use App\Jobs\ProcessHarmony;
use App\Models\BetaAssignment;
use App\Models\BetaFeedback;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CoachApplication;
use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\Comment;
use App\Models\EmailToken;
use App\Models\Engagement;
use App\Models\EsConnection;
use App\Models\ExercisePack;
use App\Models\Follow;
use App\Models\Group;
use App\Models\GroupAssignment;
use App\Models\GroupEventSong;
use App\Models\GroupMember;
use App\Models\GroupSong;
use App\Models\Harmony;
use App\Models\HarmonyLike;
use App\Models\Idea;
use App\Models\InvoiceNinjaConnection;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\LessonBooking;
use App\Models\MudeoContact;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\ProjectMember;
use App\Models\Report;
use App\Models\ReviewItem;
use App\Models\SongRequest;
use App\Models\SongRequestVote;
use App\Models\SupportMessage;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateRating;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use App\Services\EventSchedule\EventSchedule;
use App\Services\InvoiceNinja\FakeInvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Support\Accounts\EarlyAccess;
use App\Support\Audio\Peaks;
use App\Support\Gallery\Bell;
use App\Support\Sharing\HarmonyFiles;
use App\Support\Sync\SyncSeq;
use App\Support\Trust\IpHash;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The people the flow scripts sign in as through /dev/login (local only): an admin and an editor
 * whose Google IDs and emails match .env.example's dev lists. Safe to run again. Each milestone
 * adds its own demo data here; the official templates are loaded into the catalogue too.
 */
final class DemoSeeder extends Seeder
{
    /** Ada's demo project, for the flow scripts. */
    public const DEMO_PROJECT = '5b3a9c2e-1f4d-4a6b-8c7e-2d9f0a1b3c4e';

    /** Ada's own template (M7), unlisted; in the library from N3. */
    public const DEMO_TEMPLATE = 'a7d0c3b1-2e4f-4a5b-8c6d-7e8f9a0b1c2d';

    /** Grace's template in the library, waiting for review (N3). */
    public const DEMO_HELD_TEMPLATE = 'b8e1d4c2-3f50-4b6c-9d7e-8f9a0b1c2d3e';

    /** Grace's tune shared as an idea, waiting for voices (Ideas). */
    public const DEMO_IDEA_TEMPLATE = 'c9f2e5d3-4061-4c7d-8e8f-9a0b1c2d3e4f';

    public function run(): void
    {
        $this->person('dev-admin', 'Hillel (demo)', 'hillel@example.com', admin: true);
        $this->person('dev-editor', 'Eda the editor (demo)', 'editor@example.com', editor: true);

        app(SyncOfficialTemplates::class)->handle();
        $this->beta();
        $this->accounts();
        $this->cloudSave();
        $this->plus();
        $this->sharing();
        $this->together();
        $this->coaching();
        $this->eventSchedule();
        $this->books();
        $this->launch();
        $this->network();
    }

    /**
     * N1 and N3, behind their flags (turn on `gallery` and `template_library` to see them): Ada and
     * Grace with public profiles, Ada's shared song in the gallery with Grace's like, comment and
     * follow; Ada's template listed in the library with a rating, and one of Grace's waiting in
     * the review queue.
     */
    /**
     * Amazing Grace's template, the demo's own songs start from, from the songs' folders; null in
     * an install without the content (config/content.php), whose demo then has none.
     *
     * @return array<string, mixed>|null
     */
    private static function demoSong(): ?array
    {
        foreach ((array) config('templates.folders') as $folder) {
            $path = rtrim((string) $folder, '/').'/amazing-grace.json';

            if (is_file($path)) {
                return (array) json_decode((string) file_get_contents($path), true);
            }
        }

        return null;
    }

    private function network(): void
    {
        $ada = User::query()->where('email', 'ada@example.com')->first();
        $grace = User::query()->where('email', 'grace@example.com')->first();
        $sarah = User::query()->where('email', 'sarah@example.com')->first();

        $tune = self::demoSong();

        if ($tune === null || $ada === null || $grace === null || $sarah === null || Template::query()->where('slug', self::DEMO_HELD_TEMPLATE)->exists()) {
            return;
        }

        foreach ([[$ada, 'ada'], [$grace, 'grace']] as [$person, $handle]) {
            $person->forceFill(['handle' => $person->handle ?? $handle, 'adult_confirmed_at' => $person->adult_confirmed_at ?? now(), 'public_profile_at' => now(), 'trust_level_override' => 2])->save();
        }

        $song = Harmony::query()->where('user_id', $ada->id)->whereNull('deleted_at')->first();

        if ($song !== null) {
            $song->forceFill(['gallery_opt_in_at' => $song->gallery_opt_in_at ?? now(), 'rights_confirmed_at' => now(), 'gallery_held_at' => null, 'likes_count' => 1, 'comments_count' => 1])->save();
            HarmonyLike::query()->insertOrIgnore([['harmony_id' => $song->id, 'user_id' => $grace->id, 'created_at' => now()]]);
            Comment::factory()->create(['harmony_id' => $song->id, 'user_id' => $grace->id, 'body' => 'Lovely blend on the second verse.']);
            Bell::tell($ada->id, 'like', $grace, $song);
            Bell::tell($ada->id, 'comment', $grace, $song);
        }

        Follow::query()->insertOrIgnore([['follower_id' => $grace->id, 'followee_id' => $ada->id, 'created_at' => now()]]);
        Bell::tell($ada->id, 'follow', $grace);

        $declared = ['song' => 'public_domain', 'source' => 'John Newton (1779)', 'ownWork' => true, 'at' => now()->utc()->toIso8601ZuluString()];
        $template = Template::query()->with('currentVersion')->where('slug', self::DEMO_TEMPLATE)->first();
        $current = $template?->currentVersion;

        if ($template !== null && $current !== null) {
            $template->forceFill(['visibility' => 'public', 'declaration' => $declared, 'published_at' => now(), 'held_at' => null])->save();
            Library::facets($template, $current);
            (new TemplateRating)->forceFill(['template_id' => $template->id, 'user_id' => $sarah->id, 'stars' => 5])->save();
            Library::recountRatings($template);
        }

        $json = [...$tune, 'id' => self::DEMO_HELD_TEMPLATE, 'version' => 1, 'title' => 'Grace\'s River Song (demo)', 'reviewed' => true];
        $held = new Template(['slug' => self::DEMO_HELD_TEMPLATE, 'title' => $json['title'], 'access' => TemplateAccess::Free]);
        $held->forceFill(['owner_id' => $grace->id, 'visibility' => 'public', 'declaration' => $declared, 'published_at' => now(), 'held_at' => now()])->save();
        $version = new TemplateVersion(['version' => 1]);
        $version->forceFill(['template_id' => $held->id, 'reviewed' => true, 'data' => $json, 'sha256' => hash('sha256', (string) json_encode($json)), 'declaration' => $declared])->save();
        $held->forceFill(['current_version_id' => $version->id])->save();
        Library::facets($held, $version);

        // The coach directory (N4, turn on `coach_directory`): Grace featured, the hourly numbers made once.
        CoachProfile::query()->where('user_id', $grace->id)->update(['featured_at' => now()]);
        Artisan::call('app:coaches:refresh');

        // Leagues (N2, turn on `leagues`): Ada and Grace in this week's first league.
        $league = new League;
        $league->forceFill(['week' => Leagues::week(), 'tier' => 0])->save();

        foreach ([[$ada, 120], [$grace, 85]] as [$person, $xp]) {
            $person->forceFill(['league_joined_at' => now()])->save();
            (new LeagueMember)->forceFill(['league_id' => $league->id, 'user_id' => $person->id, 'xp' => $xp])->save();
        }

        // Song requests (N7, turn on `song_requests`): two asked for, one being arranged.
        foreach ([['Danny Boy', 'Traditional', 'Our choir sings it at Christmas', 'arranging', [$ada, $grace, $sarah]], ['Shenandoah', 'Traditional', null, 'requested', [$grace]]] as [$title, $writer, $why, $status, $voters]) {
            $request = new SongRequest;
            $request->forceFill(['user_id' => $voters[0]->id, 'title' => $title, 'match_key' => SongRequest::matchKey($title), 'writer' => $writer, 'why' => $why, 'status' => $status, 'votes_count' => count($voters)])->save();

            foreach ($voters as $voter) {
                (new SongRequestVote)->forceFill(['song_request_id' => $request->id, 'user_id' => $voter->id])->save();
            }
        }

        // Ideas (turn on `ideas` to see it): Grace's tune, every part open, waiting for voices.
        $json = [...$json, 'id' => self::DEMO_IDEA_TEMPLATE, 'title' => 'Grace\'s Bus Stop Hum (demo)'];
        $tune = new Template(['slug' => self::DEMO_IDEA_TEMPLATE, 'title' => $json['title'], 'access' => TemplateAccess::Free]);
        $tune->forceFill(['owner_id' => $grace->id, 'visibility' => 'private'])->save();
        $version = new TemplateVersion(['version' => 1]);
        $version->forceFill(['template_id' => $tune->id, 'reviewed' => true, 'data' => $json, 'sha256' => hash('sha256', (string) json_encode($json))])->save();
        $tune->forceFill(['current_version_id' => $version->id])->save();
        $tune->load('currentVersion');
        $voicings = Ideas::voicings($tune);
        $most = array_key_last($voicings);

        if ($most !== null) {
            (new Idea)->forceFill(['template_id' => $tune->id, 'user_id' => $grace->id, 'note' => 'Hummed it waiting for the bus. Who wants the bass?', 'voicing' => $most, 'part_keys' => $voicings[$most], 'rights_confirmed_at' => now(), 'voices_count' => 0])->save();
        }
    }

    /**
     * L: a question waiting in admin's Support, Ada's shared Amazing Grace opted into the gallery
     * (counted on the network panel), and the nightly counts made once.
     */
    private function launch(): void
    {
        if (! SupportMessage::query()->exists()) {
            (new SupportMessage)->forceFill(['email' => 'nora@example.com', 'category' => 'recording', 'body' => 'My takes come out a little late on my phone. Is there a setting?', 'context' => ['appVersion' => 'demo', 'page' => '/sing'], 'status' => SupportMessage::OPEN])->save();
        }

        // The mudeo email: its link, and a few of mudeo's singers waiting for the first batch.
        EarlyAccess::current();
        foreach ([['nina.mudeo@example.net', 'Nina'], ['omar.mudeo@example.net', 'Omar'], ['priya.mudeo@example.net', 'Priya']] as $i => [$email, $name]) {
            MudeoContact::query()->firstOrCreate(['email' => $email], ['name' => $name, 'mudeo_id' => 1000 + $i, 'mudeo_updated_at' => now()->subMonths($i + 1)]);
        }

        $ada = User::query()->where('email', 'ada@example.com')->first();
        Harmony::query()->where('user_id', $ada?->id)->whereNull('gallery_opt_in_at')->update(['gallery_opt_in_at' => now()]);
        Artisan::call('app:stats:nightly');
    }

    /**
     * M10, only while the fake Invoice Ninja is on (INVOICE_NINJA_FAKE=true, locally): Grace keeps
     * her books there (her delivered review to Ada recorded), and Stackapella's own books are
     * connected too (so admin can invoice an account for Plus).
     */
    private function books(): void
    {
        $grace = User::query()->where('email', 'grace@example.com')->first();

        if (! app(InvoiceNinja::class) instanceof FakeInvoiceNinja || $grace === null || InvoiceNinjaConnection::query()->exists()) {
            return;
        }

        $connections = app(BookConnections::class);
        $connections->connect($grace, 'in-demo-grace-books', null);
        $connections->connect(null, 'in-demo-platform-books', null);
    }

    /**
     * M9, only while the fake Event Schedule is on (EVENT_SCHEDULE_FAKE=true, locally): Grace
     * connected with her schedule picked and a 45-minute lesson on sale, which Ada has booked
     * (paid, booked in the fake, in 3 days); and Pia's choir group linked to her Riverside Choir
     * schedule, with Amazing Grace picked for the next rehearsal. `php artisan
     * app:event-schedule:fake <engagement> cancelled|completed|rescheduled` plays ES's side.
     */
    private function eventSchedule(): void
    {
        $es = app(EventSchedule::class);
        $grace = User::query()->where('email', 'grace@example.com')->first();
        $ada = User::query()->where('email', 'ada@example.com')->first();
        $pia = User::query()->where('email', 'plus@example.com')->first();

        if (! $es->isFake() || $grace === null || $ada === null || $pia === null || EsConnection::query()->where('user_id', $grace->id)->exists()) {
            return;
        }

        $connect = function (User $who, string $schedule, string $name) use ($es): EsConnection {
            $connection = new EsConnection;
            $connection->forceFill(['user_id' => $who->id, 'mode' => EsConnection::OAUTH, 'base_url' => 'https://eventschedule.com', 'es_user_id' => 'es-demo-'.$who->id, 'es_user_name' => $who->name, 'access_token' => 'fake-at-demo', 'refresh_token' => 'fake-rt-demo', 'expires_at' => now()->addYear(), 'scopes' => EventSchedule::SCOPES, 'status' => EsConnection::ACTIVE, 'schedule' => $schedule, 'schedule_name' => $name, 'schedule_timezone' => 'Europe/London'])->save();
            $hook = $es->registerWebhook($connection, route('webhooks.event-schedule', ['connection' => $connection->sqid()]), $schedule);
            $connection->forceFill(['webhook_id' => $hook['id'], 'webhook_secret' => $hook['secret']])->save();

            return $connection;
        };
        $graces = $connect($grace, 'grace-sings', 'Grace Sings');
        $pias = $connect($pia, 'riverside-choir', 'Riverside Choir');

        $lesson = new CoachOffering(['kind' => CoachOffering::LESSON, 'title' => 'A 45-minute lesson (demo)', 'description' => 'On a video call, from Event Schedule.', 'price_cents' => 4000, 'currency' => 'gbp', 'turnaround_days' => 0, 'es_appointment_type_id' => 'Ap45', 'duration_minutes' => 45]);
        $lesson->forceFill(['es_schedule' => 'grace-sings']);
        $lesson->forceFill(['coach_id' => $grace->id])->save();

        $slot = $es->slots($graces, 'Ap45', now()->addDays(3)->toDateString(), 1)[0];
        $engagement = new Engagement(['kind' => Engagement::LESSON, 'price_cents' => 4000, 'currency' => 'gbp']);
        $engagement->forceFill(['singer_id' => $ada->id, 'coach_id' => $grace->id, 'offering_id' => $lesson->id, 'status' => Engagement::BOOKED, 'fee_cents' => 600, 'refunded_cents' => 0, 'period_refunded_cents' => 0, 'stripe_payment_intent' => 'pi_demo_lesson', 'stripe_charge' => 'ch_demo_lesson'])->save();
        $booked = $es->book($graces, 'Ap45', ['externalId' => $engagement->sqid(), 'slot' => $slot, 'name' => $ada->name, 'email' => (string) $ada->email, 'guestTimezone' => 'Europe/London', 'notes' => 'My break between registers (demo).'], 'demo-lesson');
        $booking = new LessonBooking;
        $booking->forceFill(['engagement_id' => $engagement->id, 'es_connection_id' => $graces->id, 'appointment_type_id' => 'Ap45', 'es_booking_id' => $booked['id'], 'es_event_id' => $booked['eventId'], 'status' => LessonBooking::CONFIRMED, 'starts_at' => $booked['startsAt'], 'ends_at' => $booked['endsAt'], 'timezone' => 'Europe/London', 'guest_timezone' => 'Europe/London', 'cancellation_hours' => 24, 'refund_until' => Carbon::parse($booked['startsAt'])->subDay(), 'manage_url' => $booked['manageUrl'], 'ical_url' => $booked['icalUrl'], 'location_url' => $booked['locationUrl'], 'synced_at' => now()])->save();

        $group = Group::query()->where('director_id', $pia->id)->first();
        $song = $group !== null ? GroupSong::query()->where('group_id', $group->id)->first() : null;

        if ($group !== null && $song !== null) {
            $group->forceFill(['es_connection_id' => $pias->id, 'es_schedule' => 'riverside-choir', 'es_schedule_name' => 'Riverside Choir'])->save();
            $next = $es->events($pias, 'riverside-choir', now()->toDateString(), now()->addDays(60)->toDateString())[0] ?? null;

            if ($next !== null) {
                $pick = new GroupEventSong;
                $pick->forceFill(['group_id' => $group->id, 'es_event_id' => $next['id'], 'group_song_id' => $song->id])->save();
            }
        }
    }

    /**
     * M8: Grace, an approved coach (grace@example.com, demo-password-for-the-flows; her profile is
     * /coach/grace_coaches) with a review, a monthly package and a free pack; a review she has
     * delivered to Ada; and Ben's application waiting in the admin's queue. Stripe is faked locally
     * only in the tests, so buying from the demo needs Stripe's test keys.
     */
    private function coaching(): void
    {
        $ada = User::query()->where('email', 'ada@example.com')->first();
        $take = Take::query()->where('uuid', '9e2a1b3c-5d4e-4f6a-8b9c-1d2e3f4a5b6c')->first();

        if ($ada === null || $take === null || User::query()->where('email', 'grace@example.com')->exists()) {
            return;
        }

        $grace = User::query()->firstOrNew(['email' => 'grace@example.com']);
        $grace->fill(['name' => 'Grace Okafor (demo coach)', 'locale' => 'en']);
        $grace->forceFill(['handle' => 'grace_coaches', 'password' => 'demo-password-for-the-flows', 'email_verified_at' => now(), 'age_confirmed_at' => now(), 'adult_confirmed_at' => now(), 'coach_active_at' => now()])->save();
        $profile = new CoachProfile(['bio' => 'Choir director and voice teacher (demo).', 'voice_types' => ['alto', 'soprano'], 'styles' => ['choral', 'folk'], 'languages' => ['en'], 'timezone' => 'Europe/London', 'cancellation_hours' => 24]);
        $profile->forceFill(['user_id' => $grace->id, 'status' => CoachProfile::APPROVED, 'stripe_account_id' => 'acct_demo_grace', 'charges_enabled' => true, 'payouts_enabled' => true, 'details_submitted' => true, 'approved_at' => now()])->save();

        $review = new CoachOffering(['kind' => CoachOffering::REVIEW, 'title' => 'A take review', 'description' => 'I listen to your take and pin notes where it matters.', 'price_cents' => 2500, 'currency' => 'gbp', 'turnaround_days' => 5, 'max_seconds' => 180]);
        $review->forceFill(['coach_id' => $grace->id])->save();
        $package = new CoachOffering(['kind' => CoachOffering::PACKAGE, 'title' => 'Monthly coaching', 'description' => 'Four reviews a month, and messages.', 'price_cents' => 6000, 'currency' => 'gbp', 'turnaround_days' => 5, 'max_seconds' => 180, 'reviews_per_month' => 4]);
        $package->forceFill(['coach_id' => $grace->id])->save();
        $pack = new ExercisePack(['title' => 'Breath for long phrases (demo)', 'description' => 'Five exercises.', 'price_cents' => 0, 'currency' => 'gbp', 'exercises' => [['id' => 'breath-4-4', 'title' => 'Four in, four out']]]);
        $pack->forceFill(['uuid' => (string) Str::uuid(), 'coach_id' => $grace->id, 'status' => ExercisePack::APPROVED])->save();

        $engagement = new Engagement(['kind' => Engagement::REVIEW, 'price_cents' => 2500, 'currency' => 'gbp', 'turnaround_days' => 5]);
        $engagement->forceFill(['singer_id' => $ada->id, 'coach_id' => $grace->id, 'offering_id' => $review->id, 'status' => Engagement::DELIVERED, 'fee_cents' => 375, 'refunded_cents' => 0, 'stripe_payment_intent' => 'pi_demo', 'stripe_charge' => 'ch_demo', 'ended_at' => now()])->save();
        $copy = 'reviews/demo-'.Str::lower(Str::random(12)).'.flac';
        Storage::disk('private')->copy((string) $take->file_key, $copy);
        $item = new ReviewItem(['question' => 'Does my alto sit under the tune? (demo)', 'template_slug' => 'amazing-grace', 'template_version' => 1, 'part_key' => 'alto']);
        $delivered = ['comments' => [['id' => 'c0a1b2c3-d4e5-4f60-8a1b-2c3d4e5f6a7b', 'atSeconds' => 1.0, 'noteIndex' => 0, 'text' => 'A clean start.', 'mediaId' => null]], 'summary' => ['working' => 'Steady pitch.', 'toWorkOn' => ['Breathe before the phrase', 'Lighter on the top note'], 'practise' => 'Four in, four out.'], 'exercises' => [['packId' => null, 'exerciseId' => 'breath-4-4']], 'demoMediaId' => null];
        $item->forceFill(['engagement_id' => $engagement->id, 'status' => ReviewItem::DELIVERED, 'take_key' => $copy, 'sample_rate' => $take->sample_rate, 'frames' => $take->frames, 'draft' => $delivered, 'delivered' => $delivered, 'delivered_at' => now()->subHour(), 'released_at' => now()->subMinutes(50)])->save();

        $ben = User::query()->firstOrNew(['email' => 'ben@example.com']);
        $ben->fill(['name' => 'Ben Applies (demo)', 'locale' => 'en']);
        $ben->forceFill(['handle' => 'ben_applies', 'password' => 'demo-password-for-the-flows', 'email_verified_at' => now(), 'age_confirmed_at' => now(), 'adult_confirmed_at' => now()])->save();
        $application = new CoachApplication(['answers' => ['experience' => 'Ten years in barbershop quartets (demo).', 'training' => 'Self-taught.', 'voiceTypes' => ['baritone'], 'styles' => ['barbershop'], 'languages' => ['en'], 'timezone' => 'America/New_York', 'introUrl' => null], 'sample_review' => $delivered, 'agreement_version' => 1]);
        $application->forceFill(['user_id' => $ben->id, 'status' => CoachApplication::SUBMITTED])->save();
    }

    /**
     * M7: Ada's own template (unlisted: /t/<id>), Sarah singing the tenor on Ada's project, an
     * open invite (/join/demo-project-invite-for-the-flows-0001), and Pia's choir group with Ada
     * in it, assigned the alto she has recorded (/choir/demo-choir-link-for-the-flow-script).
     */
    private function together(): void
    {
        $ada = User::query()->where('email', 'ada@example.com')->first();
        $sarah = User::query()->where('email', 'sarah@example.com')->first();
        $pia = User::query()->where('email', 'plus@example.com')->first();
        $project = Project::query()->where('uuid', self::DEMO_PROJECT)->first();

        $tune = self::demoSong();

        if ($tune === null || $ada === null || $sarah === null || $pia === null || $project === null || Group::query()->where('director_id', $pia->id)->exists()) {
            return;
        }

        $uuid = self::DEMO_TEMPLATE;
        $json = [...$tune, 'id' => $uuid, 'version' => 1, 'title' => 'Ada\'s Amazing Grace (demo)', 'reviewed' => true];
        $template = new Template(['slug' => $uuid, 'title' => $json['title'], 'access' => TemplateAccess::Free]);
        $template->forceFill(['owner_id' => $ada->id, 'visibility' => 'unlisted'])->save();
        $version = new TemplateVersion(['version' => 1]);
        $version->forceFill(['template_id' => $template->id, 'reviewed' => true, 'data' => $json, 'sha256' => hash('sha256', (string) json_encode($json))])->save();
        $template->forceFill(['current_version_id' => $version->id])->save();

        $member = new ProjectMember(['role' => ProjectMember::SINGER]);
        $member->forceFill(['project_id' => $project->id, 'user_id' => $sarah->id])->save();
        $tenor = new Part(['order' => 1, 'name' => 'Tenor', 'color' => '#6fa8dc', 'volume' => 1, 'pan' => 0, 'muted' => false, 'solo' => false, 'nudge_frames' => 0, 'trim_start_frames' => 0, 'trim_end_frames' => 0, 'template_part_key' => 'tenor', 'settings' => []]);
        $tenor->forceFill(['uuid' => '1d3e5f70-8192-4a3b-9c4d-5e6f7a8b9c0d', 'project_id' => $project->id, 'version' => 1, 'created_by' => $sarah->id, 'seq' => app(SyncSeq::class)->next($ada)])->save();
        $invite = new ProjectInvite(['part_key' => 'bass', 'part_name' => 'Bass', 'expires_at' => now()->addYear()]);
        $invite->forceFill(['project_id' => $project->id, 'created_by' => $ada->id, 'token_hash' => hash('sha256', 'demo-project-invite-for-the-flows-0001')])->save();

        // Choir groups are 18 and over for now (config/usage.php): both have said so.
        foreach ([$pia, $ada] as $adult) {
            $adult->forceFill(['adult_confirmed_at' => now()])->save();
        }

        $group = new Group(['name' => 'Thursday Singers (demo)']);
        $group->forceFill(['director_id' => $pia->id, 'link_hash' => hash('sha256', 'demo-choir-link-for-the-flow-script'), 'link_created_at' => now()])->save();
        foreach ([[$pia, GroupMember::DIRECTOR], [$ada, GroupMember::MEMBER]] as [$who, $role]) {
            $row = new GroupMember(['role' => $role]);
            $row->forceFill(['group_id' => $group->id, 'user_id' => $who->id])->save();
        }
        $song = GroupSong::query()->create(['group_id' => $group->id, 'template_id' => Template::query()->official()->where('slug', 'amazing-grace')->value('id')]);
        $assignment = new GroupAssignment(['part_key' => 'alto']);
        $assignment->forceFill(['group_song_id' => $song->id, 'user_id' => $ada->id])->save();
    }

    /**
     * M6: Ada's Amazing Grace shared by link (its page /h/<id> plays; harmonies.list as Ada gives
     * the id), and one of Pia's hidden by 3 reports, waiting in the admin's queue.
     */
    private function sharing(): void
    {
        $ada = User::query()->where('email', 'ada@example.com')->first();
        $pia = User::query()->where('email', 'plus@example.com')->first();
        $project = Project::query()->where('uuid', self::DEMO_PROJECT)->first();

        if ($ada === null || $pia === null || $project === null || Harmony::query()->where('user_id', $ada->id)->exists()) {
            return;
        }

        $version = TemplateVersion::query()->whereHas('template', fn ($query) => $query->where('slug', 'amazing-grace'))->orderByDesc('version')->first();
        $outline = base64_encode(implode('', array_map(static fn (int $i): string => chr((int) (140 + 90 * sin($i / 6))), range(0, 59))));

        foreach ([[$ada, $project->id, 'Amazing Grace for Gran (demo)'], [$pia, null, 'Ode to Joy, all four parts (demo)']] as [$owner, $projectId, $title]) {
            $wav = self::tone(3.0);
            $harmony = new Harmony(['title' => $title, 'sample_rate' => 48000, 'channels' => 1, 'frames' => intdiv(strlen($wav) - 44, 2), 'upload_bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)]);
            $harmony->forceFill([
                'user_id' => $owner->id,
                'project_id' => $projectId,
                'change_id' => (string) Str::uuid(),
                'visibility' => Harmony::UNLISTED,
                'status' => Harmony::PROCESSING,
                'upload_key' => 'uploads/harmonies/demo-'.Str::lower(Str::random(12)).'.wav',
                'tracks' => [['name' => 'Alto', 'color' => '#e4a94b', 'peaks' => $outline]],
                'guide_ids' => [],
                'template_version_id' => $projectId !== null ? $version?->id : null,
                'words_part_key' => $projectId !== null ? 'soprano' : null,
                'bpm' => 80,
                'beat_zero_ms' => 0,
                // M10: Ada said our accounts may feature hers.
                'may_feature_at' => $owner->is($ada) ? now() : null,
            ])->save();
            Storage::disk('private')->put($harmony->upload_key, $wav);
            ProcessHarmony::dispatchSync($harmony->id);
        }

        $reported = Harmony::query()->where('user_id', $pia->id)->firstOrFail();
        foreach (['198.51.100.1', '198.51.100.2', '198.51.100.3'] as $i => $ip) {
            $report = new Report(['reason' => $i === 0 ? 'copyright' : 'other', 'note' => $i === 0 ? 'I think this is my recording (demo).' : '']);
            $report->forceFill(['reportable_type' => 'harmony', 'reportable_id' => $reported->id, 'reporter_key' => IpHash::of($ip), 'ip_hash' => IpHash::of($ip), 'status' => Report::OPEN])->save();
        }
        $reported->forceFill(['hidden_at' => now(), 'hidden_reason' => Harmony::HIDDEN_REPORTS])->save();
        app(HarmonyFiles::class)->sync($reported);
    }

    /**
     * M5: Pia has Plus (as Hillel would give a coach: no Stripe needed), and Plus history; this
     * month's song of the month is Silent Night.
     */
    private function plus(): void
    {
        $pia = User::query()->firstOrNew(['email' => 'plus@example.com']);
        $pia->fill(['name' => 'Pia with Plus (demo)', 'locale' => 'en']);
        $pia->forceFill(['password' => 'demo-password-for-the-flows', 'email_verified_at' => now(), 'age_confirmed_at' => now(), 'plan' => 'plus'])->save();

        Template::query()->where('slug', 'silent-night')->update(['free_from' => now()->startOfMonth()->toDateString(), 'free_until' => now()->endOfMonth()->toDateString()]);
    }

    /**
     * M4: Ada's Amazing Grace in the cloud, its alto recorded (a FLAC, with a take in the bin to
     * Undo to), so another device can pull it.
     */
    private function cloudSave(): void
    {
        $ada = User::query()->where('email', 'ada@example.com')->first();

        if ($ada === null || Project::query()->where('uuid', self::DEMO_PROJECT)->exists()) {
            return;
        }

        $seq = app(SyncSeq::class);
        $project = new Project(['title' => 'Amazing Grace (demo)', 'bpm' => 80, 'music_key' => 'G', 'time_signature' => '3/4', 'template_slug' => 'amazing-grace', 'template_version' => 1, 'settings' => ['practice' => ['seedVoices' => true]]]);
        $project->forceFill(['uuid' => self::DEMO_PROJECT, 'user_id' => $ada->id, 'version' => 1, 'seq' => $seq->next($ada)])->save();
        $part = new Part(['order' => 0, 'name' => 'Alto', 'color' => '#e4a94b', 'volume' => 1, 'pan' => 0, 'muted' => false, 'solo' => false, 'nudge_frames' => 0, 'trim_start_frames' => 0, 'trim_end_frames' => 0, 'template_part_key' => 'alto', 'settings' => []]);
        $part->forceFill(['uuid' => '0c2c7c1e-3a5b-4d6e-8f90-a1b2c3d4e5f6', 'project_id' => $project->id, 'version' => 1, 'seq' => $seq->next($ada)])->save();

        $takes = [];
        foreach (['7d1f0a2b-4c3d-4e5f-9a8b-0c1d2e3f4a5b' => 2.5, '9e2a1b3c-5d4e-4f6a-8b9c-1d2e3f4a5b6c' => 3.0] as $uuid => $seconds) {
            $wav = self::tone($seconds);
            $local = (string) tempnam(sys_get_temp_dir(), 'demo');
            file_put_contents($local, $wav);
            $key = 'takes/demo-'.substr($uuid, 0, 8).'.flac';
            app(Ffmpeg::class)->encodeFlac($local, $local.'.flac');
            Storage::disk('private')->put($key, (string) file_get_contents($local.'.flac'));

            $take = new Take(['sample_rate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'offset_frames' => 0, 'upload_bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)]);
            $take->forceFill(['uuid' => $uuid, 'user_id' => $ada->id, 'part_id' => $part->id, 'project_id' => $project->id, 'status' => Take::READY, 'upload_key' => 'uploads/takes/demo.wav', 'file_key' => $key, 'stored_bytes' => (int) filesize($local.'.flac'), 'frames_per_peak' => 512, 'peaks' => Peaks::everyFrames($local, 512), 'ready_at' => now(), 'seq' => $seq->next($ada)])->save();
            @unlink($local);
            @unlink($local.'.flac');
            $takes[] = $take;
        }

        $takes[0]->forceFill(['binned_at' => now(), 'seq' => $seq->next($ada)])->save();
        $part->forceFill(['current_take_id' => $takes[1]->id, 'version' => 2, 'seq' => $seq->next($ada)])->save();
    }

    /**
     * M3: Ada signs in with a password (ada@example.com, demo-password-for-the-flows), and Una has
     * signed up but not verified: /verify?token=demo-verify-link-for-the-flow-scripts-0001 does it.
     */
    private function accounts(): void
    {
        $ada = User::query()->firstOrNew(['email' => 'ada@example.com']);
        $ada->fill(['name' => 'Ada Lovelace (demo)', 'locale' => 'en']);
        $ada->forceFill(['handle' => 'ada_sings', 'password' => 'demo-password-for-the-flows', 'email_verified_at' => now(), 'age_confirmed_at' => now(), 'heard_from' => 'choir', 'bio' => 'Alto in a community choir.'])->save();

        $una = User::query()->firstOrNew(['email' => 'una@example.com']);
        $una->fill(['name' => 'Una Verified-Not-Yet (demo)', 'locale' => 'en']);
        $una->forceFill(['password' => 'demo-password-for-the-flows', 'email_verified_at' => null, 'age_confirmed_at' => now()])->save();

        EmailToken::query()->where('user_id', $una->id)->delete();
        $token = new EmailToken(['purpose' => EmailToken::VERIFY, 'email' => 'una@example.com', 'expires_at' => now()->addYear()]);
        $token->forceFill(['user_id' => $una->id, 'token_hash' => hash('sha256', 'demo-verify-link-for-the-flow-scripts-0001')])->save();
    }

    /**
     * M2: an open invite (/beta/demo-singer-link-for-the-flow-scripts-0001) asking Nora for Amazing
     * Grace's alto; Sarah, who accepted with her own account, consented and sent a take waiting for
     * review; and a
     * Tell Hillel note. Amazing Grace's current version counts as reviewed here, so it can be recorded.
     */
    private function beta(): void
    {
        $grace = Template::query()->where('slug', 'amazing-grace')->first();

        if ($grace === null || $grace->current_version_id === null) {
            return;
        }

        DB::table('template_versions')->where('id', $grace->current_version_id)->update(['reviewed' => true]);

        $new = BetaSinger::query()->firstOrNew(['email' => 'new.singer@example.com']);
        $new->fill(['name' => 'Nora the new singer (demo)', 'hourly_rate_cents' => 3000, 'currency' => 'USD']);
        // An open invite (/beta/demo-singer-link-for-the-flow-scripts-0001), for the sign-up flow.
        $new->forceFill(['token_hash' => hash('sha256', 'demo-singer-link-for-the-flow-scripts-0001'), 'revoked_at' => null, 'expires_at' => null, 'user_id' => null, 'accepted_at' => null])->save();
        BetaAssignment::query()->firstOrCreate(['beta_singer_id' => $new->id, 'template_id' => $grace->id, 'part_key' => 'alto'], ['minutes' => 25]);

        $sarah = BetaSinger::query()->firstOrNew(['email' => 'sarah@example.com']);
        $sarah->fill(['name' => 'Sarah Kim (demo)', 'hourly_rate_cents' => 3000, 'currency' => 'USD']);
        // Sarah has accepted with her own account (sarah@example.com, demo-password-for-the-flows).
        $account = User::query()->firstOrNew(['email' => 'sarah@example.com']);
        $account->fill(['name' => 'Sarah K.', 'locale' => 'en']);
        $account->forceFill(['password' => 'demo-password-for-the-flows', 'email_verified_at' => now(), 'age_confirmed_at' => now()])->save();
        $sarah->forceFill(['token_hash' => hash('sha256', 'demo-singer-link-for-the-flow-scripts-0002'), 'user_id' => $account->id, 'accepted_at' => now(), 'consent_version' => (int) config('beta.agreement_version'), 'consented_at' => now(), 'credit_mode' => CreditMode::Name])->save();
        $assignment = BetaAssignment::query()->firstOrCreate(['beta_singer_id' => $sarah->id, 'template_id' => $grace->id, 'part_key' => 'bass'], ['minutes' => 25]);

        if (! $assignment->submissions()->exists()) {
            $wav = self::tone(4.0);
            $submission = new BetaSubmission(['music_key' => 'G', 'sample_rate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'latency_frames' => 960, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav), 'device' => 'Mac, Chrome (demo)', 'mic' => 'Wired headset', 'scores' => ['pitchPercent' => 91], 'singer_note' => 'The low G is a stretch.']);
            $submission->forceFill(['beta_assignment_id' => $assignment->id, 'template_version_id' => $grace->current_version_id, 'part_key' => 'bass', 'status' => SubmissionStatus::Submitted, 'master_key' => 'beta/masters/demo-sarah-bass.wav', 'peaks' => array_fill(0, 400, 0.3), 'loudness_dbfs' => -13.5])->save();
            Storage::disk('private')->put($submission->master_key, $wav);
        }

        BetaFeedback::query()->firstOrCreate(['beta_singer_id' => $sarah->id, 'text' => 'The note on "sweet" feels low for the bass.'], ['template_slug' => 'amazing-grace', 'part_key' => 'bass', 'beat' => 4]);
    }

    /**
     * A plain tone as a 16-bit mono WAV, standing in for a sung take.
     */
    private static function tone(float $seconds, int $rate = 48000): string
    {
        $data = '';
        for ($i = 0, $frames = (int) ($seconds * $rate); $i < $frames; $i++) {
            $data .= pack('v', (int) round(sin(2 * M_PI * 110 * $i / $rate) * 0.3 * 32767) & 0xFFFF);
        }

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVE'.'fmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($data)).$data;
    }

    private function person(string $googleId, string $name, string $email, bool $admin = false, bool $editor = false): User
    {
        $user = User::query()->firstOrNew(['google_id' => $googleId]);
        $user->forceFill([
            'google_id' => $googleId,
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'is_admin' => $admin,
            'is_editor' => $editor,
        ])->save();

        return $user;
    }
}
