<?php

declare(strict_types=1);

namespace App\Actions\SingingTest;

use App\Models\SingingTest;
use App\Models\User;
use App\Support\SingingTest\Scoring;
use App\Support\SingingTest\SingingTests;
use Illuminate\Support\Collection;

/**
 * singingTests.list: the account's tests, newest first, each at the current scoring version (one scored with an older
 * version is scored again and kept, so the history moves together).
 */
final class ListSingingTests
{
    /**
     * @return Collection<int, SingingTest>
     */
    public function handle(User $user): Collection
    {
        SingingTests::ensureOn($user);
        $tests = SingingTest::query()->where('user_id', $user->id)->orderByDesc('taken_at')->orderByDesc('id')->get();

        foreach ($tests as $test) {
            if ($test->scored_version !== Scoring::VERSION) {
                $test->rescore()->save();
            }
        }

        return $tests;
    }
}
