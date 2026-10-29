<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CoachOffering;
use App\Models\CoachProfile;
use App\Models\User;
use App\Services\Coaching\CoachingGateway;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Coaching in the tests: the fake Stripe Connect, an approved coach who can sell, and Stripe's
 * webhook as Stripe would send it.
 */
trait Coaching
{
    use CloudSave;

    protected FakeCoachingGateway $connect;

    protected function fakeConnect(): FakeCoachingGateway
    {
        $this->connect = new FakeCoachingGateway;
        $this->app->instance(CoachingGateway::class, $this->connect);

        return $this->connect;
    }

    protected function as(User $user): User
    {
        $user = $user->fresh() ?? $user;
        $this->actingAs($user);

        return $user;
    }

    /**
     * An approved coach whose Stripe account can take charges, with a review offering.
     *
     * @return array{0: User, 1: CoachOffering}
     */
    protected function coach(string $kind = CoachOffering::REVIEW): array
    {
        $coach = User::factory()->create(['name' => 'Grace Okafor', 'handle' => 'grace_'.Str::lower(Str::random(6)), 'coach_active_at' => now()]);
        CoachProfile::factory()->create(['user_id' => $coach->id]);
        $offering = CoachOffering::factory()->create(['coach_id' => $coach->id, 'kind' => $kind, 'reviews_per_month' => $kind === CoachOffering::PACKAGE ? 2 : null]);

        return [$coach->fresh() ?? $coach, $offering];
    }

    /**
     * A singer with a recorded take of their own: [singer, take UUID].
     *
     * @return array{0: User, 1: string}
     */
    protected function singerWithTake(): array
    {
        $singer = $this->owner();
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();

        return [$singer, $take->uuid];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function stripeEvent(string $type, array $object): TestResponse
    {
        return $this->postJson('/webhooks/stripe', ['id' => 'evt_'.uniqid(), 'type' => $type, 'data' => ['object' => $object]]);
    }

    protected function paid(string $sessionId): void
    {
        $this->stripeEvent('checkout.session.completed', ['id' => $sessionId, 'metadata' => ['kind' => 'coaching']])->assertOk();
    }
}
