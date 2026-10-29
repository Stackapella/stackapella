<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Mail\Account\ResetPassword;
use App\Mail\Account\SignInLinkMail;
use App\Mail\Account\VerifyEmailAddress;
use App\Models\SignInLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An address that only looks like someone's (an accent, a full-width letter) is a different address
 * (docs/security-review.md, the look-alike addresses): it never finds their account, so their reset,
 * sign-in or verification link can't be mailed to a domain that looks like theirs. And account
 * mail goes to the account's own address.
 */
final class LookAlikeEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private static function lookAlikes(): array
    {
        return ['victim@exämple.com', 'vìctim@example.com', 'victim@ｅxample.com'];
    }

    public function test_a_look_alike_address_never_finds_the_account(): void
    {
        Mail::fake();
        $victim = User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('the-victims-own-passphrase'), 'email_verified_at' => now()]);

        foreach (self::lookAlikes() as $address) {
            $this->postJson('/api/auth/password/forgot', ['email' => $address])->assertStatus(202);
            $this->postJson('/api/auth/link', ['email' => $address])->assertStatus(202);
            $this->postJson('/api/auth/sign-in', ['email' => $address, 'password' => 'the-victims-own-passphrase'])->assertUnauthorized();
        }

        Mail::assertNotQueued(ResetPassword::class);
        Mail::assertNotSent(ResetPassword::class);
        // A look-alike is a new address of its own: its sign-in link (if sign-ups are open) is for
        // it, never for the victim's account.
        Mail::assertNotQueued(SignInLinkMail::class, static fn (SignInLinkMail $mail): bool => $mail->hasTo('victim@example.com'));
        $this->assertFalse(SignInLink::query()->where('email', 'victim@example.com')->exists());
        $this->assertSame(SignInLink::query()->count(), SignInLink::query()->whereIn('email', self::lookAlikes())->count());
        $this->assertSame(1, User::query()->where('email', 'victim@example.com')->count());
        $this->assertSame('victim@example.com', $victim->refresh()->email);
    }

    public function test_an_unverified_account_isnt_verified_through_a_look_alike(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('someone-elses-passphrase'), 'email_verified_at' => null]);

        $this->postJson('/api/auth/verification', ['email' => 'victim@exämple.com'])->assertStatus(202);

        Mail::assertNotQueued(VerifyEmailAddress::class);
        Mail::assertNotSent(VerifyEmailAddress::class);
    }

    public function test_account_mail_goes_to_the_accounts_own_address(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ada@example.com', 'email_verified_at' => now()]);

        $this->postJson('/api/auth/password/forgot', ['email' => '  ADA@Example.com '])->assertStatus(202);

        Mail::assertQueued(ResetPassword::class, static fn (ResetPassword $mail): bool => $mail->hasTo('ada@example.com'));
    }

    public function test_a_name_is_one_line(): void
    {
        $this->postJson('/api/auth/register', ['name' => "Ada\r\nBcc: someone@example.com", 'email' => 'ada@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 1, 'birthYear' => 1990])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $ada = User::factory()->create()->fresh() ?? throw new \LogicException;
        $this->actingAs($ada)->patchJson('/api/account', ['name' => "Ada\nLovelace"])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->actingAs($ada)->patchJson('/api/account', ['name' => 'Ada Lovelace'])->assertOk();
    }
}
