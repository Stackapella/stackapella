<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\HandleHold;
use App\Models\User;
use App\Support\Accounts\HandleRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HandleRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shape_of_a_handle(): void
    {
        $rules = new HandleRules;

        $this->assertNull($rules->problem('ada_sings'));
        foreach (['ab', '1ada', 'Ada', 'ada-sings', 'ada sings', str_repeat('a', 21), 'adá'] as $bad) {
            $this->assertSame('invalid', $rules->problem($bad), $bad);
        }
    }

    public function test_our_names_and_look_alikes_are_reserved(): void
    {
        $rules = new HandleRules;

        foreach (['stackapella', 'stackape11a', 'the_stackapella_team', 'hi11el', 'admin', 'adm1n', 'support', 'help_desk', 'official'] as $handle) {
            $this->assertSame('reserved', $rules->problem($handle), $handle);
        }

        foreach (['steam', 'helper', 'modern_alto', 'meadow', 'betatron'] as $fine) {
            $this->assertNull($rules->problem($fine), $fine);
        }
    }

    public function test_offensive_words_are_refused_but_innocent_ones_are_not(): void
    {
        $rules = new HandleRules;

        $this->assertSame('invalid', $rules->problem('bitch_please'));
        $this->assertSame('invalid', $rules->problem('a_fag'));
        $this->assertSame('invalid', $rules->problem('assh0le_singer'));
        foreach (['peacock', 'grape_vine', 'dickens_fan', 'scunthorpe'] as $fine) {
            $this->assertNull($rules->problem($fine), $fine);
        }
    }

    public function test_a_taken_or_held_handle_is_taken_but_your_own_is_fine(): void
    {
        $user = User::factory()->create(['handle' => 'ada_sings']);
        HandleHold::factory()->create(['handle' => 'gone_singer']);

        $this->assertSame('taken', (new HandleRules)->problem('ada_sings'));
        $this->assertNull((new HandleRules)->problem('ada_sings', $user));
        $this->assertSame('taken', (new HandleRules)->problem('gone_singer'));
    }
}
