<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Mail\Account\VerifyEmailAddress;
use App\Models\User;
use App\Models\UserLearning;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
