<?php

declare(strict_types=1);

namespace App\Actions\SingingTest;

use App\Exceptions\Refusal;
use App\Models\SingingTest;
use App\Models\User;
use App\Support\SingingTest\SingingTests;

/**
 * singingTests.update: the account's first counted test marked a warm-up afterwards (it stays in the history, never
 * counted), or counted again. Any other test answers `422 not_first`.
 */
final class MarkWarmUp
{
    public function handle(User $user, string $id, bool $warmUp): SingingTest
    {
        SingingTests::ensureOn($user);
        $test = SingingTest::query()->where('user_id', $user->id)->where('uuid', $id)->first() ?? throw new Refusal(404, 'not_found');
        $first = SingingTest::query()->where('user_id', $user->id)->where('status', '!=', SingingTest::PRACTICE)->orderBy('taken_at')->orderBy('id')->value('id');

        if ($test->id !== $first) {
            throw new Refusal(422, 'not_first');
        }

        $test->forceFill(['warm_up' => $warmUp])->rescore()->save();

        return $test;
    }
}
