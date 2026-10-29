<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Answered "13 or over", as an email sign-up does at once.
            'age_confirmed_at' => now(),
            'password' => null,
            'google_id' => (string) fake()->unique()->numerify('1############'),
            'is_admin' => false,
            'is_editor' => false,
            'locale' => 'en',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An admin who passes all three checks (config admin.admin_google_ids and admin_emails in
     * phpunit.xml list these values).
     */
    public function admin(): static
    {
        return $this->state(fn (): array => [
            'is_admin' => true,
            'google_id' => 'admin-google-id',
            'email' => 'admin@example.test',
        ]);
    }

    public function editor(): static
    {
        return $this->state(fn (): array => [
            'is_editor' => true,
            'google_id' => 'editor-google-id',
            'email' => 'editor@example.test',
        ]);
    }

    /**
     * An email account: a password, and no Google.
     */
    public function withPassword(string $password = 'a-fine-long-passphrase-42'): static
    {
        return $this->state(fn (): array => ['password' => $password, 'google_id' => null]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['suspended_at' => now()]);
    }
}
