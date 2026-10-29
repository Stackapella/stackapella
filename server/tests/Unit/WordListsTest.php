<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Trust\WordLists;
use Tests\TestCase;

final class WordListsTest extends TestCase
{
    public function test_common_passwords_are_known_and_others_arent(): void
    {
        $this->assertTrue(WordLists::isCommonPassword('password'));
        $this->assertTrue(WordLists::isCommonPassword('Qwerty123'));
        $this->assertFalse(WordLists::isCommonPassword('harmony-sopranos-sing-4'));
    }

    public function test_disposable_domains_and_their_subdomains(): void
    {
        $this->assertTrue(WordLists::isDisposableEmail('x@mailinator.com'));
        $this->assertTrue(WordLists::isDisposableEmail('x@foo.mailinator.com'));
        $this->assertFalse(WordLists::isDisposableEmail('singer@gmail.com'));
    }
}
