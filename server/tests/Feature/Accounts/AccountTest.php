<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Jobs\BuildExport;
use App\Mail\Account\ConfirmNewEmail;
use App\Mail\Account\EmailChanging;
use App\Mail\Account\ExportReady;
use App\Models\DataExport;
use App\Models\HandleHold;
use App\Models\Part;
use App\Models\Project;
use App\Models\PushDevice;
use App\Models\Score;
use App\Models\SupportMessage;
use App\Models\Take;
use App\Models\User;
use App\Support\Accounts\SignInSession;
use App\Support\Flags;
use App\Support\Legal\Terms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class AccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-fine-long-passphrase-42';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function signedIn(User $user, bool $recently = true): User
    {
        // As a request would load it: every column (a new factory model has only what it set).
        $user = $user->fresh() ?? $user;
        $this->flushSession();
        $this->actingAs($user)->withSession($recently ? [SignInSession::SIGNED_IN_AT => now()->getTimestamp()] : []);

        return $user;
    }

    public function test_the_account_needs_a_sign_in_and_shows_its_own_fields(): void
    {
        $this->assertMatchesContract($this->getJson('/api/account'), 'account.show', 401);

        $user = $this->signedIn(User::factory()->withPassword()->create(['handle' => 'ada_sings']));
        $response = $this->getJson('/api/account');

        $this->assertMatchesContract($response, 'account.show', 200);
        $response->assertJsonPath('data.staff', null)->assertJsonPath('data.handle', 'ada_sings')->assertJsonPath('data.methods', ['google' => false, 'apple' => false, 'eventSchedule' => false, 'password' => true])->assertJsonPath('data.plan', 'free');
        $this->assertSame($user->sqid(), $response->json('data.id'));
    }

    public function test_the_profile_changes_and_asks_its_one_time_questions_once(): void
    {
        $user = $this->signedIn(User::factory()->create(['age_confirmed_at' => null]));

        $response = $this->patchJson('/api/account', ['name' => ' Ada ', 'handle' => 'ada_sings', 'bio' => 'Alto.', 'timezone' => 'Europe/London', 'xHandle' => 'ada', 'blueskyHandle' => 'ada.bsky.social', 'heardFrom' => 'choir']);
        $this->assertMatchesContract($response, 'account.update', 200);
        $response->assertJsonPath('data.name', 'Ada')->assertJsonPath('data.birthGiven', false)->assertJsonPath('data.heardFrom', 'choir');
        $this->patchJson('/api/account', ['ageConfirmed' => true])->assertStatus(422)->assertJsonValidationErrors('ageConfirmed');

        $this->patchJson('/api/account', ['heardFrom' => 'tiktok'])->assertJsonPath('data.heardFrom', 'choir');
        // The results screen's newer answers are accepted too.
        $this->signedIn(User::factory()->create());
        $this->patchJson('/api/account', ['heardFrom' => 'app_store'])->assertOk()->assertJsonPath('data.heardFrom', 'app_store');
        $this->patchJson('/api/account', ['heardFrom' => 'myspace'])->assertStatus(422);
        // The old "13 or over" sets nothing: only when they were born answers the age question.
        $this->assertNull($user->refresh()->age_confirmed_at);
    }

    public function test_a_handle_must_be_free_kind_and_not_look_like_ours(): void
    {
        User::factory()->create(['handle' => 'taken_one']);
        $this->signedIn(User::factory()->create());

        foreach (['taken_one', 'stackape11a', 'Has Spaces', 'x', 'admin'] as $handle) {
            $response = $this->patchJson('/api/account', ['handle' => $handle]);
            $this->assertMatchesContract($response, 'account.update', 422);
            $response->assertJsonValidationErrors('handle');
        }

        $this->assertMatchesContract($this->patchJson('/api/account', ['bio' => 'see https://spam.test']), 'account.update', 422);

        $check = $this->getJson('/api/account/handle?handle=taken_one');
        $this->assertMatchesContract($check, 'account.handle.check', 200);
        $check->assertJsonPath('data.available', false)->assertJsonPath('data.reason', 'taken');
        $this->getJson('/api/account/handle?handle=fresh_voice')->assertJsonPath('data.available', true)->assertJsonPath('data.reason', null);
    }

    public function test_a_changed_handle_is_held_for_its_owner_for_30_days(): void
    {
        $ada = $this->signedIn(User::factory()->create(['handle' => 'ada_sings']));
        $this->patchJson('/api/account', ['handle' => 'ada_alto'])->assertOk();

        $this->assertNotNull(HandleHold::query()->where('handle', 'ada_sings')->where('user_id', $ada->id)->value('held_until'));

        $this->signedIn(User::factory()->create());
        $this->getJson('/api/account/handle?handle=ada_sings')->assertJsonPath('data.reason', 'taken');

        $this->signedIn($ada);
        $this->getJson('/api/account/handle?handle=ada_sings')->assertJsonPath('data.available', true);
        $this->patchJson('/api/account', ['handle' => 'ada_sings'])->assertOk();
        $this->assertFalse(HandleHold::query()->where('handle', 'ada_sings')->exists());

        // Only the latest handle is held: changing again and again doesn't hoard names.
        $this->patchJson('/api/account', ['handle' => 'ada_one'])->assertOk();
        $this->patchJson('/api/account', ['handle' => 'ada_two'])->assertOk();
        $this->assertSame(['ada_one'], HandleHold::query()->where('user_id', $ada->id)->pluck('handle')->all());

        $this->travel(31)->days();
        $this->signedIn(User::factory()->create());
        $this->getJson('/api/account/handle?handle=ada_alto')->assertJsonPath('data.available', true);
    }

    public function test_changing_the_email_confirms_the_new_address_and_tells_the_old_one(): void
    {
        $user = $this->signedIn(User::factory()->withPassword()->create(['email' => 'old@example.com']), recently: false);

        $this->assertMatchesContract($this->postJson('/api/account/email', ['email' => 'new@example.com']), 'account.email.update', 403);
        $this->assertMatchesContract($this->postJson('/api/account/email', ['email' => 'new@example.com', 'password' => 'not-the-password']), 'account.email.update', 422);
        $this->assertMatchesContract($this->postJson('/api/account/email', ['email' => 'new@example.com', 'password' => self::PASSWORD]), 'account.email.update', 202);

        $this->getJson('/api/account')->assertJsonPath('data.email', 'old@example.com')->assertJsonPath('data.pendingEmail', 'new@example.com');
        Mail::assertQueued(EmailChanging::class, fn (EmailChanging $mail): bool => $mail->hasTo('old@example.com') && $mail->newEmail === 'new@example.com');

        $token = null;
        Mail::assertQueued(ConfirmNewEmail::class, function (ConfirmNewEmail $mail) use (&$token): bool {
            $token = $mail->token;

            return $mail->hasTo('new@example.com');
        });

        $confirmed = $this->postJson('/api/account/email/confirm', ['token' => $token]);
        $this->assertMatchesContract($confirmed, 'account.email.confirm', 200);
        $confirmed->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.pendingEmail', null);
        $this->assertSame('new@example.com', $user->refresh()->email);
        $this->assertMatchesContract($this->postJson('/api/account/email/confirm', ['token' => $token]), 'account.email.confirm', 410);
    }

    public function test_this_wasnt_me_brings_the_old_address_back_and_signs_everyone_out(): void
    {
        $user = $this->signedIn(User::factory()->withPassword()->create(['email' => 'old@example.com']));
        $rememberToken = $user->remember_token;
        $this->postJson('/api/account/email', ['email' => 'thief@example.com'])->assertStatus(202);

        $revert = null;
        Mail::assertQueued(EmailChanging::class, function (EmailChanging $mail) use (&$revert): bool {
            $revert = $mail->revertToken;

            return true;
        });
        $confirm = null;
        Mail::assertQueued(ConfirmNewEmail::class, function (ConfirmNewEmail $mail) use (&$confirm): bool {
            $confirm = $mail->token;

            return true;
        });
        $this->postJson('/api/account/email/confirm', ['token' => $confirm])->assertOk();

        // The thief also tries to keep the old address by signing up with it.
        $squatter = User::factory()->withPassword()->unverified()->create(['email' => 'old@example.com']);

        $reverted = $this->postJson('/api/account/email/revert', ['token' => $revert]);
        $this->assertMatchesContract($reverted, 'account.email.revert', 200);
        $reverted->assertJsonPath('data.email', 'old@example.com');

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertNull($user->password, 'Whoever changed it may know the password.');
        $this->assertNull($squatter->refresh()->email);
        $this->assertMatchesContract($this->postJson('/api/account/email/revert', ['token' => $revert]), 'account.email.revert', 410);
    }

    public function test_an_undo_link_does_not_take_an_address_someone_verified_and_keeps_working(): void
    {
        $this->signedIn(User::factory()->withPassword()->create(['email' => 'old@example.com']));
        $this->postJson('/api/account/email', ['email' => 'next@example.com'])->assertStatus(202);
        $revert = null;
        Mail::assertQueued(EmailChanging::class, function (EmailChanging $mail) use (&$revert): bool {
            $revert = $mail->revertToken;

            return true;
        });
        $user = User::query()->where('email', 'old@example.com')->firstOrFail();
        $user->forceFill(['email' => 'next@example.com'])->save();
        $owner = User::factory()->create(['email' => 'old@example.com']);

        $this->assertMatchesContract($this->postJson('/api/account/email/revert', ['token' => $revert]), 'account.email.revert', 409);

        $owner->forceFill(['email' => 'elsewhere@example.com'])->save();
        $this->postJson('/api/account/email/revert', ['token' => $revert])->assertOk();
    }

    public function test_a_later_change_never_cancels_the_first_undo_link(): void
    {
        $this->signedIn(User::factory()->withPassword()->create(['email' => 'victim@example.com']));
        $this->postJson('/api/account/email', ['email' => 'thief@example.com'])->assertStatus(202);
        $this->postJson('/api/account/email/confirm', ['token' => $this->lastConfirmToken()])->assertOk();
        $victimsUndo = $this->revertTokenSentTo('victim@example.com');

        // The intruder changes it again, and gets an undo link of their own.
        $this->signedIn(User::query()->where('email', 'thief@example.com')->firstOrFail());
        $this->postJson('/api/account/email', ['email' => 'thief2@example.com'])->assertStatus(202);
        $this->postJson('/api/account/email/confirm', ['token' => $this->lastConfirmToken()])->assertOk();
        $thiefsUndo = $this->revertTokenSentTo('thief@example.com');

        $this->postJson('/api/account/email/revert', ['token' => $thiefsUndo])->assertOk();
        $this->postJson('/api/account/email/revert', ['token' => $victimsUndo])->assertOk();

        $this->assertSame(1, User::query()->where('email', 'victim@example.com')->count());
        $this->assertMatchesContract($this->postJson('/api/account/email/revert', ['token' => $thiefsUndo]), 'account.email.revert', 410);
    }

    public function test_an_address_someone_has_looks_pending_like_any_other(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $this->signedIn(User::factory()->create());

        $this->postJson('/api/account/email', ['email' => 'taken@example.com'])->assertStatus(202);

        $this->getJson('/api/account')->assertJsonPath('data.pendingEmail', 'taken@example.com');
        Mail::assertNotQueued(ConfirmNewEmail::class);
    }

    public function test_the_password_can_be_guessed_no_more_than_at_sign_in(): void
    {
        $this->signedIn(User::factory()->withPassword()->create(), recently: false);

        for ($i = 0; $i < 10; $i++) {
            $this->deleteJson('/api/account', ['password' => 'wrong-password-'.$i])->assertStatus(422);
        }

        $this->assertMatchesContract($this->deleteJson('/api/account', ['password' => self::PASSWORD]), 'account.delete', 429);
    }

    private function lastConfirmToken(): string
    {
        $token = '';
        Mail::assertQueued(ConfirmNewEmail::class, function (ConfirmNewEmail $mail) use (&$token): bool {
            $token = $mail->token;

            return true;
        });

        return $token;
    }

    private function revertTokenSentTo(string $email): string
    {
        $token = '';
        Mail::assertQueued(EmailChanging::class, function (EmailChanging $mail) use ($email, &$token): bool {
            if ($mail->hasTo($email)) {
                $token = $mail->revertToken;
            }

            return true;
        });

        return $token;
    }

    public function test_the_password_is_changed_with_the_current_one_and_signs_out_other_sessions(): void
    {
        $user = $this->signedIn(User::factory()->withPassword()->create(), recently: false);
        $rememberToken = $user->remember_token;

        $this->assertMatchesContract($this->putJson('/api/account/password', ['password' => 'another-fine-passphrase']), 'account.password.update', 403);
        $this->assertMatchesContract($this->putJson('/api/account/password', ['currentPassword' => 'wrong-password-1', 'password' => 'another-fine-passphrase']), 'account.password.update', 422);
        $this->assertMatchesContract($this->putJson('/api/account/password', ['currentPassword' => self::PASSWORD, 'password' => 'qwertyuiop']), 'account.password.update', 422);

        $response = $this->putJson('/api/account/password', ['currentPassword' => self::PASSWORD, 'password' => 'another-fine-passphrase']);
        $this->assertMatchesContract($response, 'account.password.update', 200);
        $this->assertTrue(Hash::check('another-fine-passphrase', (string) $user->refresh()->password));
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_google_account_adds_a_password_after_a_recent_sign_in(): void
    {
        $user = $this->signedIn(User::factory()->create());

        $this->putJson('/api/account/password', ['password' => 'another-fine-passphrase'])->assertOk()->assertJsonPath('data.methods.password', true);
        $this->assertNotNull($user->refresh()->password);
    }

    public function test_a_method_is_removed_but_never_the_last(): void
    {
        $user = $this->signedIn(User::factory()->withPassword()->create(['google_id' => 'g-1']));

        $response = $this->deleteJson('/api/account/methods/google');
        $this->assertMatchesContract($response, 'account.methods.delete', 200);
        $response->assertJsonPath('data.methods.google', false);

        $this->assertMatchesContract($this->deleteJson('/api/account/methods/password'), 'account.methods.delete', 409);
        $this->assertNotNull($user->refresh()->password);
        $this->deleteJson('/api/account/methods/github')->assertNotFound();

        $this->signedIn(User::factory()->withPassword()->create(['google_id' => 'g-2']), recently: false);
        $this->assertMatchesContract($this->deleteJson('/api/account/methods/google'), 'account.methods.delete', 403);
    }

    public function test_the_picture_becomes_a_square_jpeg_on_the_cdn(): void
    {
        $user = $this->signedIn(User::factory()->create());
        Http::fake();

        $response = $this->post('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 400, 300)], ['Accept' => 'application/json']);
        $this->assertMatchesContract($response, 'account.avatar.update', 200);
        $key = (string) $user->refresh()->avatar_key;
        Storage::disk('media')->assertExists($key);
        $this->assertSame([256, 256], array_slice((array) getimagesizefromstring((string) Storage::disk('media')->get($key)), 0, 2));
        $this->assertStringEndsWith($key, (string) $response->json('data.avatarUrl'));

        $this->assertMatchesContract($this->post('/api/account/avatar', ['avatar' => UploadedFile::fake()->create('me.pdf', 10, 'application/pdf')], ['Accept' => 'application/json']), 'account.avatar.update', 422);
        $this->assertMatchesContract($this->post('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('huge.png', 7000, 10)], ['Accept' => 'application/json']), 'account.avatar.update', 422);

        $removed = $this->deleteJson('/api/account/avatar');
        $this->assertMatchesContract($removed, 'account.avatar.delete', 200);
        $removed->assertJsonPath('data.avatarUrl', null);
        Storage::disk('media')->assertMissing($key);
    }

    public function test_my_data_is_prepared_in_the_background_and_linked_for_7_days(): void
    {
        $user = $this->signedIn(User::factory()->create(['handle' => 'ada_sings']));

        $none = $this->getJson('/api/account/export');
        $this->assertMatchesContract($none, 'account.export.show', 200);
        $none->assertJsonPath('data.status', 'none');

        Queue::fake();
        $created = $this->postJson('/api/account/export');
        $this->assertMatchesContract($created, 'account.export.create', 202);
        $created->assertJsonPath('data.status', 'preparing');
        $this->postJson('/api/account/export')->assertStatus(202);
        Queue::assertPushed(BuildExport::class, 1);

        (new BuildExport((int) DataExport::query()->value('id')))->handle();
        Mail::assertQueued(ExportReady::class, fn (ExportReady $mail): bool => $mail->hasTo((string) $user->email));

        $ready = $this->getJson('/api/account/export');
        $this->assertMatchesContract($ready, 'account.export.show', 200);
        $ready->assertJsonPath('data.status', 'ready');
        $this->assertNotNull($ready->json('data.url'));

        $this->travel(8)->days();
        $this->getJson('/api/account/export')->assertJsonPath('data.status', 'none')->assertJsonPath('data.url', null);
    }

    public function test_my_data_holds_every_kind_of_record_without_secrets(): void
    {
        $user = $this->signedIn(User::factory()->create(['email' => 'ada@example.com', 'age_confirmed_at' => now()]));
        $project = Project::factory()->create(['user_id' => $user->id]);
        $part = Part::factory()->create(['project_id' => $project->id]);
        $take = Take::factory()->create(['part_id' => $part->id, 'peaks' => [0.5, 0.25]]);
        $part->forceFill(['current_take_id' => $take->id])->save();
        Score::factory()->create(['user_id' => $user->id, 'summary' => ['pitchPercent' => 81]]);
        SupportMessage::factory()->create(['user_id' => null, 'email' => 'ada@example.com', 'body' => 'My takes come out late.']);
        PushDevice::factory()->create(['user_id' => $user->id]);

        Queue::fake();
        $this->postJson('/api/account/export')->assertStatus(202);
        (new BuildExport((int) DataExport::query()->value('id')))->handle();

        $zip = new ZipArchive;
        $path = (string) tempnam(sys_get_temp_dir(), 'export');
        file_put_contents($path, (string) Storage::disk('private')->get((string) DataExport::query()->value('file_key')));
        $zip->open($path);
        $read = static fn (string $name): mixed => json_decode((string) $zip->getFromName($name), true);

        $this->assertNotNull($read('account.json')['ageConfirmedAt']);
        $this->assertSame($take->uuid, $read('projects.json')[0]['parts'][0]['currentTakeId']);
        $this->assertSame(81, $read('records/pitch-history.json')[0]['summary']['pitchPercent']);
        $this->assertSame('My takes come out late.', $read('records/support-messages.json')[0]['body']);
        $this->assertCount(1, $read('records/reminder-devices.json'));
        $this->assertSame([], $read('records/lessons.json'));

        // Never another person's id, a secret, an outline or a file's key.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $text = (string) $zip->getFromIndex($i);
            foreach (['"peaks"', 'token', 'p256dh', 'endpoint', 'file_key', 'upload_key', 'secret', 'password'] as $never) {
                $this->assertStringNotContainsString($never, $text, (string) $zip->getNameIndex($i));
            }
        }

        $zip->close();
        @unlink($path);
    }

    public function test_a_failed_or_lost_export_never_blocks_the_next(): void
    {
        $this->signedIn(User::factory()->create());
        Queue::fake();

        $this->postJson('/api/account/export')->assertStatus(202);
        (new BuildExport((int) DataExport::query()->value('id')))->failed(null);
        $failed = $this->getJson('/api/account/export');
        $this->assertMatchesContract($failed, 'account.export.show', 200);
        $failed->assertJsonPath('data.status', 'failed');

        $this->postJson('/api/account/export')->assertJsonPath('data.status', 'preparing');
        $this->travel(2)->hours();
        $this->postJson('/api/account/export')->assertStatus(202);
        $this->assertSame(3, DataExport::query()->count());
        Queue::assertPushed(BuildExport::class, 3);
    }

    public function test_the_chosen_theme_follows_the_account(): void
    {
        $user = $this->signedIn(User::factory()->create());
        $this->getJson('/api/account')->assertJsonPath('data.appearance', null);

        // The mode alone first: the colour stays not chosen (the mode's own).
        $first = $this->patchJson('/api/account', ['appearance' => ['mode' => 'dark']]);
        $this->assertMatchesContract($first, 'account.update', 200);
        $first->assertJsonPath('data.appearance', ['mode' => 'dark', 'colour' => null, 'accent' => null, 'customAccent' => null, 'skin' => 'game']);
        $chosen = $this->patchJson('/api/account', ['appearance' => ['mode' => 'dark', 'colour' => 'midnight']]);
        $chosen->assertJsonPath('data.appearance', ['mode' => 'dark', 'colour' => 'midnight', 'accent' => null, 'customAccent' => null, 'skin' => 'game']);
        $this->patchJson('/api/account', ['appearance' => ['mode' => 'light']])->assertJsonPath('data.appearance', ['mode' => 'light', 'colour' => 'midnight', 'accent' => null, 'customAccent' => null, 'skin' => 'game']);
        $this->patchJson('/api/account', ['appearance' => ['mode' => 'light', 'accent' => 'teal', 'customAccent' => null]])->assertJsonPath('data.appearance', ['mode' => 'light', 'colour' => 'midnight', 'accent' => 'teal', 'customAccent' => null, 'skin' => 'game']);
        $this->assertMatchesContract($this->patchJson('/api/account', ['appearance' => ['accent' => 'neon']]), 'account.update', 422);
        // A choice saved before accents came reads as the palette's own.
        $user->forceFill(['appearance' => ['mode' => 'dark', 'colour' => 'plum']])->save();
        $this->getJson('/api/account')->assertJsonPath('data.appearance', ['mode' => 'dark', 'colour' => 'plum', 'accent' => null, 'customAccent' => null, 'skin' => 'game']);

        // Their own accent: a hex with "custom" (stored lower case), cleared by any other accent.
        $this->patchJson('/api/account', ['appearance' => ['accent' => 'custom']])->assertStatus(422)->assertJsonValidationErrors('appearance.customAccent');
        $this->patchJson('/api/account', ['appearance' => ['accent' => 'custom', 'customAccent' => 'red']])->assertStatus(422)->assertJsonValidationErrors('appearance.customAccent');
        $custom = $this->patchJson('/api/account', ['appearance' => ['accent' => 'custom', 'customAccent' => '#3A7BD5']]);
        $this->assertMatchesContract($custom, 'account.update', 200);
        $custom->assertJsonPath('data.appearance.customAccent', '#3a7bd5');
        $this->patchJson('/api/account', ['appearance' => ['accent' => 'indigo']])->assertJsonPath('data.appearance.accent', 'indigo')->assertJsonPath('data.appearance.customAccent', null);

        // Game or serious: kept with the rest, game until chosen.
        $this->patchJson('/api/account', ['appearance' => ['skin' => 'serious']])->assertJsonPath('data.appearance.skin', 'serious')->assertJsonPath('data.appearance.accent', 'indigo');
        $this->patchJson('/api/account', ['appearance' => ['mode' => 'dark']])->assertJsonPath('data.appearance.skin', 'serious');
        $this->assertMatchesContract($this->patchJson('/api/account', ['appearance' => ['skin' => 'silly']]), 'account.update', 422);

        $this->assertMatchesContract($this->patchJson('/api/account', ['appearance' => ['colour' => 'neon']]), 'account.update', 422);
        $this->patchJson('/api/account', ['appearance' => ['mode' => 'dark', 'font' => 'big']])->assertStatus(422);
        $this->assertMatchesContract($this->getJson('/api/account'), 'account.show', 200);
    }

    public function test_someone_who_hasnt_answered_the_age_question_cant_upload_share_join_or_buy(): void
    {
        $this->signedIn(User::factory()->create(['age_confirmed_at' => null]));

        $this->assertMatchesContract($this->postJson('/api/takes', []), 'takes.create', 403);
        $this->assertMatchesContract($this->postJson('/api/harmonies', []), 'harmonies.create', 403);
        $this->assertMatchesContract($this->postJson('/api/join/nope'), 'invites.accept', 403);
        $this->assertMatchesContract($this->postJson('/api/choir/nope'), 'groups.join', 403);
        $this->assertMatchesContract($this->postJson('/api/engagements', []), 'engagements.create', 403);
        $this->postJson('/api/takes', [])->assertJsonPath('code', 'age_unconfirmed');

        // Answered (when they were born): through to the usual checks.
        $this->patchJson('/api/account', ['birthMonth' => 5, 'birthYear' => 1990])->assertOk();
        $this->postJson('/api/takes', [])->assertStatus(422);
    }

    public function test_someone_who_hasnt_answered_the_age_question_may_always_delete(): void
    {
        $user = $this->signedIn(User::factory()->create(['age_confirmed_at' => null]), recently: false);

        $deleted = $this->deleteJson('/api/account');
        $this->assertMatchesContract($deleted, 'account.delete', 204);
        $this->assertModelMissing($user);
        // Signed out, and its remember cookie cleared, without the row coming back.
        $this->assertGuest();
        $deleted->assertCookieExpired(Auth::guard('web')->getRecallerName());
        $this->assertModelMissing($user);
    }

    public function test_deleting_the_account_needs_the_password_and_holds_the_handle(): void
    {
        $user = $this->signedIn(User::factory()->withPassword()->create(['handle' => 'ada_sings']), recently: false);

        $this->assertMatchesContract($this->deleteJson('/api/account'), 'account.delete', 403);
        $this->assertMatchesContract($this->deleteJson('/api/account', ['password' => self::PASSWORD]), 'account.delete', 204);

        $this->assertGuest();
        $this->assertModelMissing($user);
        $this->assertNull(HandleHold::query()->where('handle', 'ada_sings')->value('held_until'));
        $this->assertTrue(HandleHold::query()->where('handle', 'ada_sings')->exists());
    }

    public function test_the_plus_courses_show_to_staff_until_their_switch_opens_them_to_everyone(): void
    {
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $shown = $this->getJson('/api/account');
        $this->assertMatchesContract($shown, 'account.show', 200);
        $shown->assertJsonPath('data.plusCourses', false);

        $this->actingAs(User::factory()->create(['is_editor' => true])->fresh() ?? throw new \LogicException);
        $this->getJson('/api/account')->assertJsonPath('data.plusCourses', true);

        app(Flags::class)->set('plus_courses', 'on', null, 'Opening the Plus courses');
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->getJson('/api/account')->assertJsonPath('data.plusCourses', true);
    }

    public function test_every_new_account_agrees_to_the_terms_and_accepts_a_new_version_once(): void
    {
        Terms::forget();
        $current = Terms::current();
        $ada = User::factory()->create()->fresh() ?? throw new \LogicException;
        $this->assertSame($current, $ada->terms_version);
        $this->assertNotNull($ada->terms_accepted_at);

        // An account from before acceptance was recorded, and a new version.
        $ada->forceFill(['terms_version' => null, 'terms_accepted_at' => null])->save();
        $this->actingAs($ada->fresh() ?? throw new \LogicException);
        $this->getJson('/api/account')->assertJsonPath('data.terms', ['accepted' => null, 'current' => $current, 'acceptedAt' => null]);

        $stale = $this->postJson('/api/account/terms', ['version' => $current + 1]);
        $this->assertMatchesContract($stale, 'account.terms.accept', 409);
        $stale->assertJsonPath('code', 'terms_changed')->assertJsonPath('current.version', $current);
        $this->assertMatchesContract($this->postJson('/api/account/terms', []), 'account.terms.accept', 422);

        $accepted = $this->postJson('/api/account/terms', ['version' => $current]);
        $this->assertMatchesContract($accepted, 'account.terms.accept', 200);
        $accepted->assertJsonPath('data.terms.accepted', $current);
        $this->assertNotNull($accepted->json('data.terms.acceptedAt'));
    }
}
