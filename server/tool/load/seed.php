<?php

declare(strict_types=1);

/*
 * Realistic data for a local load test (docs/performance.md, "Local performance pass"), into a
 * scratch database whose name ends in "_perf" (it refuses any other). Run after migrating it fresh
 * and loading the official songs:
 *
 *   export DB_DATABASE=stackapella_perf
 *   php artisan migrate:fresh --force && php artisan app:templates:sync
 *   php tool/load/seed.php [scale]       # scale 1: about 1,700 people, 3,800 songs in progress, 30,000 takes
 *
 * It prints the access tokens of five heavy singers (150 projects each), for beta.js's TOKENS.
 * Uses the factories, so it needs the dev packages (never run on a server).
 */

use App\Actions\Counts\ActiveSingers;
use App\Models\CheckoutSession;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\CoachingPayment;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupSong;
use App\Models\Harmony;
use App\Models\HarmonyLike;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\Part;
use App\Models\PartScore;
use App\Models\Project;
use App\Models\StoreSubscription;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserLearning;
use App\Support\Accounts\AppTokens;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
if (! str_ends_with($database, '_perf')) {
    fwrite(STDERR, "seed: {$database} isn't a scratch database (its name must end in _perf).\n");
    exit(1);
}

$scale = max(1, (int) ($argv[1] ?? 1));
$started = microtime(true);
$say = static fn (string $what) => fwrite(STDERR, sprintf("  %-38s %6.1f s\n", $what, microtime(true) - $started));
$handles = 0;
$person = static function (array $with = []) use (&$handles): User {
    $handles++;

    return User::factory()->create(['handle' => 'singer_'.$handles, 'adult_confirmed_at' => now(), 'public_profile_at' => now(), 'trust_level_override' => 2, ...$with]);
};

// The network features on, so their reads do their work rather than answer 404.
foreach (['gallery', 'ideas', 'virtual_choir'] as $flag) {
    app(Flags::class)->set($flag, 'on', null, 'Local load test');
}

$official = Template::query()->with('currentVersion')->whereNull('owner_id')->whereNotNull('current_version_id')->get();
if ($official->isEmpty()) {
    fwrite(STDERR, "seed: no official songs: run php artisan app:templates:sync first.\n");
    exit(1);
}
$say("{$official->count()} official songs there");

// People: most just singing, some with a public library song.
$people = collect(range(1, 1500 * $scale))->map(fn () => $person());
$say('people');

$data = (array) $official->first()?->currentVersion?->data;
foreach ($people->random(300 * $scale) as $i => $owner) {
    $template = Template::factory()->create(['owner_id' => $owner->id, 'visibility' => 'public', 'published_at' => now()->subDays($i % 90), 'title' => 'Library song '.$i]);
    $version = TemplateVersion::factory()->create(['template_id' => $template->id, 'reviewed' => true, 'data' => [...$data, 'id' => $template->slug, 'title' => $template->title]]);
    $template->forceFill(['current_version_id' => $version->id])->save();
}
$say('library songs');

// Songs in progress: a part's current take and one older take in the bin.
$song = static function (User $owner, int $parts = 4): void {
    $project = Project::factory()->create(['user_id' => $owner->id, 'title' => 'Practice '.fake()->word()]);
    foreach (range(0, $parts - 1) as $order) {
        $part = Part::factory()->create(['project_id' => $project->id, 'order' => $order]);
        Take::factory()->create(['part_id' => $part->id, 'project_id' => $project->id, 'user_id' => $owner->id, 'binned_at' => now()->subDays(3)]);
        $take = Take::factory()->create(['part_id' => $part->id, 'project_id' => $project->id, 'user_id' => $owner->id]);
        $part->forceFill(['current_take_id' => $take->id])->save();
    }
};
foreach ($people->random(300 * $scale) as $owner) {
    foreach (range(1, 10) as $n) {
        $song($owner);
    }
}
$say('songs in progress');

// Five heavy singers, each with 150 songs, the course under way, and an app sign-in.
$heavy = collect(range(1, 5))->map(fn () => $person(['plan' => 'plus']));
foreach ($heavy as $owner) {
    foreach (range(1, 150) as $n) {
        $song($owner);
    }
    UserLearning::factory()->create(['user_id' => $owner->id, 'progress' => ['xp' => 840, 'lessons' => array_fill_keys(array_map(static fn (int $i): string => 'u1-l'.$i, range(1, 12)), ['stars' => 3])]]);
}
$say('heavy singers');

// Groups: a director, 15 singers, 5 songs.
foreach ($people->random(150 * $scale) as $director) {
    $group = Group::factory()->create(['director_id' => $director->id, 'name' => 'Choir of '.$director->handle]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $director->id, 'role' => GroupMember::DIRECTOR]);
    foreach ($people->where('id', '!=', $director->id)->random(15) as $member) {
        GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $member->id]);
    }
    foreach ($official->random(min(5, $official->count())) as $tune) {
        GroupSong::factory()->create(['group_id' => $group->id, 'template_id' => $tune->id]);
    }
}
$say('groups');

// The gallery: shared harmonies with likes and comments.
foreach (range(1, 1500 * $scale) as $i) {
    $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $people->random()->id, 'gallery_opt_in_at' => now()->subMinutes($i), 'rights_confirmed_at' => now(), 'visibility' => Harmony::UNLISTED, 'title' => 'Harmony '.$i]);
    Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
    Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
    app(HarmonyFiles::class)->sync($harmony);
    foreach ($people->random(3) as $fan) {
        HarmonyLike::factory()->create(['harmony_id' => $harmony->id, 'user_id' => $fan->id]);
    }
    if ($i % 2 === 0) {
        Comment::factory()->create(['harmony_id' => $harmony->id, 'user_id' => $people->random()->id]);
    }
}
$say('gallery');

// Ideas, with a voice or three on each.
foreach (range(1, 300 * $scale) as $i) {
    $writer = $people->random();
    $tune = Template::factory()->create(['owner_id' => $writer->id, 'title' => 'Idea '.$i]);
    $version = TemplateVersion::factory()->create(['template_id' => $tune->id, 'reviewed' => true, 'data' => [...$data, 'id' => $tune->slug]]);
    $tune->forceFill(['current_version_id' => $version->id])->save();
    $idea = Idea::factory()->create(['user_id' => $writer->id, 'template_id' => $tune->id]);
    foreach (['soprano', 'alto', 'tenor'] as $n => $key) {
        if ($n <= $i % 3) {
            IdeaVoice::factory()->create(['idea_id' => $idea->id, 'user_id' => $people->random()->id, 'part_key' => $key]);
        }
    }
}
$say('ideas');

// The virtual choir: Amazing Grace, 400 voices in the mix.
$grace = $official->firstWhere('slug', 'amazing-grace') ?? $official->first();
$choir = Choir::factory()->create(['template_id' => $grace?->id]);
foreach ($people->random(400) as $n => $singer) {
    ChoirVoice::factory()->create(['choir_id' => $choir->id, 'user_id' => $singer->id, 'part_key' => ['soprano', 'alto', 'tenor', 'bass'][$n % 4], 'in_mix_at' => now()]);
}
$say('the virtual choir');

// Admin's numbers (docs/performance.md, the 2026-11-01 pass): 180 days of the anonymous daily
// counts, practice scores, leaderboard scores, subscriptions and coaching sales.
$kinds = array_values(array_unique([...(array) config('counts.kinds'), ...(array) config('counts.funnel')]));
$rows = [];
foreach (range(0, 179) as $ago) {
    $day = now()->subDays($ago)->toDateString();
    foreach ($kinds as $kind) {
        foreach (['guest', 'account'] as $audience) {
            foreach (['GB', 'US', 'DE', 'IL', 'FR', 'CA', 'AU', ''] as $country) {
                $rows[] = ['day' => $day, 'kind' => $kind, 'audience' => $audience, 'country' => $country, 'variant' => '', 'source' => '', 'count' => random_int(0, 40)];
                if (count($rows) >= 2000) {
                    DB::table('usage_counts')->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        }
    }
}
DB::table('usage_counts')->insertOrIgnore($rows);
$say('180 days of daily counts');

$ids = $people->pluck('id')->all();
$rows = [];
foreach (range(1, 20000 * $scale) as $i) {
    $at = now()->subMinutes(random_int(0, 60 * 24 * 60))->toDateTimeString();
    $rows[] = ['uuid' => (string) Str::uuid(), 'user_id' => $ids[array_rand($ids)], 'take_uuid' => null, 'template_slug' => 'amazing-grace', 'template_version' => 1, 'part_key' => 'alto', 'lesson' => null, 'recorded_at' => $at, 'summary' => '{"pitchPercent":80}', 'created_at' => $at];
    if (count($rows) >= 2000) {
        DB::table('scores')->insert($rows);
        $rows = [];
    }
}
DB::table('scores')->insert($rows);
$say('practice scores');

if ($grace !== null && $grace->current_version_id !== null) {
    foreach ($people->random(min(1200, $people->count())) as $singer) {
        PartScore::factory()->create(['user_id' => $singer->id, 'template_id' => $grace->id, 'template_version_id' => $grace->current_version_id, 'part_key' => 'alto']);
    }
    $say('leaderboard scores');
}

foreach ($people->random(400 * $scale) as $n => $subscriber) {
    $created = now()->subDays(random_int(1, 300));
    DB::table('subscriptions')->insert(['user_id' => $subscriber->id, 'type' => 'default', 'stripe_id' => 'sub_'.Str::random(14), 'stripe_status' => $n % 9 === 0 ? 'canceled' : ($n % 13 === 0 ? 'trialing' : 'active'), 'stripe_price' => $n % 3 === 0 ? 'price_year' : 'price_month', 'quantity' => 1,
        'trial_ends_at' => $n % 4 === 0 ? $created->copy()->addDays(7) : null, 'ends_at' => $n % 9 === 0 ? $created->copy()->addDays(60) : null, 'created_at' => $created, 'updated_at' => $created]);
    CheckoutSession::factory()->create(['user_id' => $subscriber->id, 'currency' => ['usd', 'eur', 'gbp'][$n % 3], 'completed_at' => $created]);
}
StoreSubscription::factory()->count(100 * $scale)->create();
CoachingPayment::factory()->count(300 * $scale)->create(['paid_at' => fn () => now()->subDays(random_int(0, 360))]);
$say('subscriptions and sales');

app(ActiveSingers::class)->fill();
$say('the active singers snapshot');

$tokens = $heavy->map(fn (User $owner): string => app(AppTokens::class)->issue($owner, ['name' => 'Load test', 'platform' => 'ios'], 'password', '127.0.0.1')['accessToken']);
fwrite(STDERR, sprintf("Done in %.0f s: %d people, %d projects, %d takes, %d harmonies.\n", microtime(true) - $started, User::query()->count(), Project::query()->count(), Take::query()->count(), Harmony::query()->count()));
echo $tokens->implode(','), "\n";
