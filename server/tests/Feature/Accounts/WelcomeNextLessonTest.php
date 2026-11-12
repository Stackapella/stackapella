<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Mail\Account\VerifyEmailAddress;
use App\Models\User;
use App\Models\UserLearning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The verify email's one more line (Hillel, 2026-11-11, docs/retention-plan.md row 7: "Yes"): the free course's next
 * lesson, linked with ?ref=welcome. One email, nothing more.
 */
final class WelcomeNextLessonTest extends TestCase
{
    use RefreshDatabase;

    private static function mail(): string
    {
        return (string) (new VerifyEmailAddress('token', 'Ana'))->to('ana@example.com')->render();
    }

    public function test_a_new_account_is_pointed_at_lesson_one_and_one_with_a_star_at_the_next(): void
    {
        $ana = User::factory()->create(['email' => 'ana@example.com']);

        $html = self::mail();
        $this->assertStringContainsString('Your next lesson: Lesson 1, Your comfortable note', $html);
        $this->assertStringContainsString('/learn/1-comfortable?ref=welcome', $html);

        UserLearning::factory()->create(['user_id' => $ana->id, 'progress' => ['lessons' => ['1-comfortable' => ['stars' => 2]]]]);
        $html = self::mail();
        $this->assertStringContainsString('Your next lesson: Lesson 2, Match a note', $html);
        $this->assertStringContainsString('/learn/1-match?ref=welcome', $html);
        // The confirm button is still the main one, first.
        $this->assertLessThan(strpos($html, '?ref=welcome'), strpos($html, '/verify?token=token'));
    }

    public function test_a_guest_who_finished_lessons_before_signing_up_is_sent_to_the_one_after(): void
    {
        // The account wall comes after lesson 1, and the guest's progress reaches the account only once verified: the
        // app sends what the device finished (frontend's Spanish walk, 2026-11-12: "names Lesson 1 to a guest who
        // already did it").
        Mail::fake();
        $form = ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990, 'elapsedMs' => 45000, 'nickname' => ''];
        $this->postJson('/api/auth/register', [...$form, 'doneLessons' => ['1-comfortable', 'not-a-lesson']])->assertStatus(202);

        $sent = null;
        Mail::assertQueued(VerifyEmailAddress::class, function (VerifyEmailAddress $mail) use (&$sent): bool {
            $sent = $mail;

            return true;
        });
        $this->assertInstanceOf(VerifyEmailAddress::class, $sent);
        $html = (string) $sent->render();
        $this->assertStringContainsString('Your next lesson: Lesson 2, Match a note', $html);
        $this->assertStringContainsString('/learn/1-match?ref=welcome', $html);

        // Asked again from the same screen, with the same lessons: the same next lesson (another account, as one
        // address's mails are capped).
        User::factory()->create(['email' => 'cy@example.com', 'email_verified_at' => null, 'password' => 'a-fine-long-passphrase-42']);
        $this->postJson('/api/auth/verification', ['email' => 'cy@example.com', 'doneLessons' => ['1-comfortable']])->assertStatus(202);
        Mail::assertQueued(VerifyEmailAddress::class, fn (VerifyEmailAddress $mail): bool => $mail->doneLessons === ['1-comfortable']);

        // Anything but lesson ids is refused, as the rest of the form is.
        $this->postJson('/api/auth/register', [...$form, 'email' => 'bo@example.com', 'doneLessons' => ['<b>']])->assertStatus(422);
    }

    public function test_the_next_lesson_reads_in_spanish(): void
    {
        $html = (string) (new VerifyEmailAddress('token', 'Ana', ['1-comfortable']))->to('ana@example.com')->locale('es')->render();

        $this->assertStringContainsString('Tu próxima lección: Lección 2, ', $html);
        $this->assertStringContainsString('Empezar la lección', $html);
        $this->assertStringNotContainsString('Your next lesson', $html);
    }
}
